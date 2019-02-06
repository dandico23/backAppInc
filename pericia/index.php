<?php

require '../vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$app = new \Slim\App();

require 'conectadb.php';

//diretorio para upload de arquivos
$container = $app->getContainer();
$container['upload_directory'] = __DIR__ . '/uploads';

//retorna o JSON de um formulário - recebe o id do formulário como parâmetro
$app->get('/formularios/{id}', function(Request $request, Response $response, array $args) {
	$id_form = $args['id'];
	
	$pdo = db_connect();
	$sql = "SELECT form_estruct FROM forms where form_id=$id_form";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$form = $stmt->fetch(PDO::FETCH_OBJ);
	
	$retorno  = $form->form_estruct;
	$retorno2 = json_decode($retorno);
	
	$return = $response->withJson($retorno2)->withHeader('Content-type', 'application/json');
	return $return;	
});
//cria tabelas do banco de dados
$app->post('/formulario/criaDB', function(Request $request, Response $response) {
	//insere o JSON enviado na tabela de formulários
	$teste =  $request->getBody();
	$formulario = json_decode($request->getBody());
	$tabela = $formulario->form_name;
	$estrutura = json_encode($formulario);	
	$pdo = db_connect();
	$dataCadastro = date('Y-m-d H:i:s');
	$sql = "INSERT INTO forms(form_name,form_version,form_estruct, data_added) values('$tabela','1.0','$teste','$dataCadastro')";
	$stmt=$pdo->prepare($sql);
	$stmt->execute();	

	
	//busca a lista de campos do formulário enviado
	$stmt->closeCursor();
	$sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(form_estruct, '$.steps[*].components[*].data_name')) as campos FROM forms where form_name='$tabela'";
	$stmt=$pdo->prepare($sql);
	$stmt->execute();	
	$campos = $stmt->fetch(PDO::FETCH_OBJ);
	$listaCampos = json_decode($campos->campos);

	//busca a lista de tipos
	$stmt->closeCursor();
	$sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(form_estruct, '$.steps[*].components[*].component_type')) as camposTipo FROM forms where form_name='$tabela'";
	$stmt=$pdo->prepare($sql);
	$stmt->execute();	
	$camposTipo = $stmt->fetch(PDO::FETCH_OBJ);
	$listaTipos = json_decode($camposTipo->camposTipo);

	//cria um array com nome=>tipo_campo
	$listaCampoTipo = array_combine($listaCampos, $listaTipos);

	$stmt->closeCursor();
	//inicia a construção do SQL que cria a(s) tabelas que receberão os dados de formulários preenchidos
	$query = "CREATE TABLE $tabela(id_form int(11) NOT NULL,
	form_name varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
	";

	//cria a lista de campos
	for($cont=0; $cont <= count($listaCampoTipo); $cont++){
		$query = $query . " " . $listaCampos[$cont];
		if($listaTipos[$cont] == "text" or $listaTipos[$cont] == "data"  or $listaTipos[$cont] == "scanner" or $listaTipos[$cont] == "audiorec" or $listaTipos[$cont]=="checkbox"){
			$query = $query . " varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL";
		}
		if($listaTipos[$cont]=="camera"){
			$query = $query . " varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			";
			$query = $query . " " ."leg_". $listaCampos[$cont];
			$query = $query . " varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL";
		}
		if($listaTipos[$cont] == "veiculo" or $listaTipos[$cont] == "geoloc"){
			$query = $query . " text COLLATE utf8mb4_unicode_ci NOT NULL";
		}
		if($listaTipos[$cont] == "date"){
			$query = $query . " date NOT NULL";
		}
		if($cont < count($listaCampoTipo)-1){
			$query = $query  . ',
			';
		}
	} 
	$query = $query . ") 
	ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
	ALTER TABLE $tabela ADD PRIMARY KEY (id_form);
  ALTER TABLE $tabela MODIFY id_form int(11) NOT NULL AUTO_INCREMENT;";
	//prepara e executa a sql que cria a tabela
	$stmt=$pdo->prepare($query);
	$stmt->execute();

});
//recebe os dados de um formulário
$app->post('/formulario/envio', function(Request $request, Response $response) {
	$texto = $request->getParsedBody();
	
	//busca a lista de campos do formulário enviado
 	$pdo = db_connect();
	$sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(form_estruct, '$.steps[*].components[*].data_name')) as campos FROM forms where form_name='$texto[form_name]'";
	$stmt=$pdo->prepare($sql);

	$stmt->execute();	
	$campos = $stmt->fetch(PDO::FETCH_OBJ);
	$listaCampos = json_decode($campos->campos);

	//busca a lista de tipos
	$stmt->closeCursor();
	$sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(form_estruct, '$.steps[*].components[*].component_type')) as camposTipo FROM forms where form_name='$texto[form_name]'";
	$stmt=$pdo->prepare($sql);
	$stmt->execute();	
	$camposTipo = $stmt->fetch(PDO::FETCH_OBJ);
	$listaTipos = json_decode($camposTipo->camposTipo);

	//cria um array com nome=>tipo_campo
	$listaCampoTipo = array_combine($listaCampos, $listaTipos);

	//cria a lista de campos
 	for($cont=0; $cont <= count($listaCampoTipo); $cont++){
		if($listaTipos[$cont]=='camera'){
			$nomeCampos = $nomeCampos . $listaCampos[$cont]. ','. 'leg_'. $listaCampos[$cont];
		}else{
			$nomeCampos = $nomeCampos . $listaCampos[$cont];
		}
		if($cont < count($listaCampoTipo)-1){
			$nomeCampos = $nomeCampos . ',';
		}
	} 
	//faz o upload dos arquivos e salva o nome do arquivo	
	$directory = $this->get('upload_directory');
	$uploadedFiles = $request->getUploadedFiles();		
	foreach($listaCampos as $campo){		
		if($listaCampoTipo[$campo]=='camera' or $listaCampoTipo[$campo]=='audiorec'){			
			if($uploadedFiles[$campo]){
			$uploadedFile = $uploadedFiles[$campo];
			if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
				$filename = moveUploadedFile($directory, $uploadedFile);
				$texto[$campo] = $filename;
			}
			}else{$texto[$campo] = null;}
			
		}
	}
	//CRIA A LSITA DE VALORES
	for($cont=0; $cont <= count($listaCampoTipo); $cont++){
		
		if($listaTipos[$cont]=='camera'){
			$valor = $texto[$listaCampos[$cont]];
			$valorCampos = $valorCampos . "'" . $valor . "'" . "," . "'". $texto['leg_' . $listaCampos[$cont]];
		}else{
			$valor = $texto[$listaCampos[$cont]];
			$valorCampos = $valorCampos . "'" . $valor;
		}
		if($cont < count($listaCampoTipo)-1){
			$valorCampos = $valorCampos . "'" .',';
		}
	} 
	//INSERE OS DADOS NO BANCO DE DADOS
 	$stmt->closeCursor();
 	$sql = "INSERT INTO $texto[form_name] (form_name,$nomeCampos) values('$texto[form_name]',$valorCampos)";
	$stmt=$pdo->prepare($sql);
	$stmt->execute();
	$id_envio =  $pdo->lastInsertId(); 
 	$stmt->closeCursor();
	$sql = "INSERT INTO form_to_pop (form_name, id_form_name) values('$texto[form_name]',$id_envio)";
   	$stmt=$pdo->prepare($sql);
   	$stmt->execute();
	$id_formPop =  $pdo->lastInsertId();  


	$retorno = array(
		"number" => $id_formPop 
	);

	$zip = new ZipArchive();
	if( $zip->open( 'uploads/'. $id_formPop . '.zip' , ZipArchive::CREATE )  === true){
		$zip->addFromString('leia-me.txt' , "Este zip contem todos arquivos enviados ao servidor" );
		foreach($listaCampos as $campo){		
			if($listaCampoTipo[$campo]=='camera' or $listaCampoTipo[$campo]=='audiorec'){
				if($texto[$campo]){			
					$zip->addFile(  'uploads/'. $texto[$campo] , $texto[$campo] );
				}
				 
			}   
			
		}
	}
	$zip->close();
	$return = $response->withJson($retorno)->withHeader('Content-type', 'application/json');
	return $return;

});
//consulta banco de dados simulado do denatran
$app->get('/denatran/{placa}', function(Request $request, Response $response, array $args) {
	$id_form = $args['placa'];
	
	$pdo = db_connect();
	$sql = "SELECT * FROM `base_denatran`where placa='$id_form' ";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$form = $stmt->fetch(PDO::FETCH_OBJ);
	
	//$retorno  = utf8_encode($form->form_estruct);
	//$retorno2 = json_decode($retorno);
	//var_dump($form);
	$return = $response->withJson($form)->withHeader('Content-type', 'application/json');
	return $return;
});
//faz login do usuário
$app->post('/usuario/login', function(Request $request, Response $response) {
	$credencial = json_decode($request->getBody());
	$mensagem = new \stdClass();

	$pdo = db_connect();
	$sql = "SELECT * FROM perito where matricula='$credencial->matricula'";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$dadosAcesso = $stmt->fetch(PDO::FETCH_OBJ);


	$mensagem = new \stdClass();
	//verifica se o usuário possui uma senha cadastrada
	if($stmt->rowCount()>0){
 		if(is_null($dadosAcesso->senha)){
			//se o usuário não possui senha cadastrada retorna o erro
			$mensagem->status = false;
			$mensagem->mensagem = "Usuário não possui senha cadastrada";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);
		}else if($dadosAcesso->senha==$credencial->pass){
			$mensagem->status = true;
			$mensagem->mensagem = "dados validados";
			$mensagem->nome = $dadosAcesso->nome;
			$mensagem->token = $dadosAcesso->token;
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(200);


		}else{
			$mensagem->status = false;
			$mensagem->mensagem = "Dados incorretos";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);
		
		}
	}else{
		$mensagem->status = false;
			$mensagem->mensagem = "Usuário inexistente";
			$mensagem->token = $dadosAcesso->token;
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);
	}
	return $return;
});
//gera o PIN pada cadastro do usuário
$app->post('/usuario/cadastro', function(Request $request, Response $response) {
	$credencial = json_decode($request->getBody());
	$mensagem = new \stdClass();

	$pdo = db_connect();
	$sql = "SELECT * FROM perito where matricula='$credencial->matricula'";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$dadosAcesso = $stmt->fetch(PDO::FETCH_OBJ);
	if($stmt->rowCount()>0){
		if(is_null($dadosAcesso->senha)){
			$pin = rand (1000, 9999);
			$stmt->closeCursor();
			$sql = "update perito set pin='$pin' where matricula='$credencial->matricula'";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();

			//envio do email
			$mail = new PHPMailer(true);                              // Passing `true` enables exceptions
			try {
				//Server settings
				$mail->SMTPDebug = 2;                                 // Enable verbose debug output
				$mail->isSMTP();                                      // Set mailer to use SMTP
				$mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
				$mail->SMTPAuth = true;                               // Enable SMTP authentication
				$mail->Username = 'moises.dandico23@gmail.com';                 // SMTP username
				$mail->Password = 'br-*102319';                           // SMTP password
				$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
				$mail->Port = 587;                                    // TCP port to connect to
			
				//Recipients
				$mail->setFrom('moises.dandico23@gmail.com','Suporte' );
				$mail->addAddress($dadosAcesso->email, $dadosAcesso->nome);     // Add a recipient
				$mail->addAddress('moises.dandico23@gmail.com');               // Name is optional
				$mail->addReplyTo('moises.dandico23@gmail.com', 'Suporte');
				$mail->addCC('cc@example.com');
				$mail->addBCC('bcc@example.com');
			
				//Attachments
				//$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
				//$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
			
				//Content
				$mail->isHTML(true);                                  // Set email format to HTML
				$mail->Subject = 'PIN de acesso';
				$mail->Body    = 'Informe o PIN na tela do aplicativo <b>' . $pin . '</b>';
				$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
			
				$mail->send();
				//echo 'Message has been sent';
			} catch (Exception $e) {
				//echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
			}

			$mensagem->status = true;
			$mensagem->mensagem = "O PIN foi gerado e enviado por E-MAIL";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(200);

		}else{
			$mensagem->status = false;
			$mensagem->mensagem = "Usuário já possui uma senha";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);
		}
	}else{
		$mensagem->status = false;
		$mensagem->mensagem = "Usuário não cadastrado no sistema";
		$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);		
	}
	return $return;
});
//valida o PIN informado
$app->post('/usuario/validaPin', function(Request $request, Response $response) {
	$credencial = json_decode($request->getBody());
	$mensagem = new \stdClass();

	$pdo = db_connect();
	$sql = "SELECT * FROM perito where matricula='$credencial->matricula'";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$dadosAcesso = $stmt->fetch(PDO::FETCH_OBJ);
	if($stmt->rowCount()>0){
		if($credencial->pin==$dadosAcesso->pin){

			$mensagem->status = true;
			$mensagem->mensagem = "O PIN é valido";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(200);

		}else if($credencial->pin!=$dadosAcesso->pin){
			$mensagem->status = false;
			$mensagem->mensagem = "PIN invalido";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);
		}
	}else{
		$mensagem->status = false;
		$mensagem->mensagem = "Erro no envio das informações Verifique o usuário e o PIN";
		$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);		
	}
	return $return;
});
//cadastra uma nova senha para o usuário
$app->post('/usuario/geraSenha', function(Request $request, Response $response) {
	$credencial = json_decode($request->getBody());
	$mensagem = new \stdClass();

 	$pdo = db_connect();
	$sql = "SELECT * FROM perito where matricula='$credencial->matricula' and pin=$credencial->pin";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$dadosAcesso = $stmt->fetch(PDO::FETCH_OBJ);
 	if($stmt->rowCount()>0){
		 $stmt->closeCursor();
		 $token= md5($credencial->pass);
		$sql = "update perito set senha='$credencial->pass',token='$token' where matricula='$credencial->matricula' and pin='$credencial->pin'";
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		 if($stmt->rowCount()>0){
			$mensagem->status = true;
			$mensagem->mensagem = "O usuário foi cadastrado com sucesso";
			$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(200);
		} 
	} else{
		$mensagem->status = false;
		$mensagem->mensagem = "Erro no envio das informações Verifique o usuário e o PIN";
		$return = $response->withJson($mensagem)->withHeader('Content-type', 'application/json')->withStatus(206);		
	}
	return $return;   
});


/**
 * Move o arquivo carregado para o diretório de upload e atribui a ele um nome exclusivo
 * para evitar a substituição de um arquivo carregado anteriormente.
 *
 * @param string $directory diretório para o qual o arquivo será movido
 * @param UploadedFile $uploaded arquivo carregado por upload
 * @return string nome do arquivo do arquivo movido
 */
function moveUploadedFile($directory, UploadedFile $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // veja http://php.net/manual/en/function.random-bytes.php
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

$app->run();