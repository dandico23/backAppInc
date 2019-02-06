<?php

require '../vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;

$app = new \Slim\App();

#arquivo com funcao db_connect() que retorna uma conexao dbo com o BD
require 'conectadb.php';

$container = $app->getContainer();
$container['upload_directory'] = __DIR__ . '/uploads';


$app->get('/formularios/', function(Request $request, Response $response) {


	
	
	$componentes = array(
			"component_type"	=>"text",
			"data_name"			=> "first_name",
			"label"				=> "Qual é o seu primeiro nome?",
			"hint"				=> "informe o primeiro nome do usuário",
			"default_value"		=> "Fulano",
			"required"			=> "true",
			"required_message"	=>"O nome do usuário é obrigatório",
			"length_min"		=> 3,
			"lenght_max"		=> 60,
			"invalid_text"		=>"O nome não atende os requisitos minimos"	
	);
	
	$tcomponentes[] = $componentes;
	
	$componentes = array(
			"component_type"	=>"text",
			"data_name"			=> "last_name",
			"label"				=> "Qual é o seu sobrenome nome?",
			"hint"				=> "informe o sobrenome do usuário",
			"default_value"		=> "De Tal",
			"required"			=> "false",
			"required_message"	=>"",
			"length_min"		=> 3,
			"lenght_max"		=> 60,
			"invalid_text"		=>"O sobrenome não atende os requisitos minimos"	
	);

	$tcomponentes[] = $componentes;
	
	$form = array(
		"form_name"				=>"Formulario de Identificação",
		"form_version"			=>"1.0",
		"components"			=> $tcomponentes
	
	);

	
	$return = $response->withJson($form)->withHeader('Content-type', 'application/json');
	return $return;

});


$app->post('/texto/', function(Request $request, Response $response) {
	$texto = $request->getParsedBody();
	
	return $texto[texto1];

});

$app->post('/formularios/envios', function(Request $request, Response $response) {
	$texto = $request->getParsedBody();
	$directory = $this->get('upload_directory');
	
	$uploadedFiles = $request->getUploadedFiles();

    // handle single input with single file upload
    $uploadedFile = $uploadedFiles['example1'];
/*     if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);
        $response->write('uploaded ' . $filename . '<br/>');
    } */

 	$pdo = db_connect();
	
	$sql = "INSERT INTO forms_enviados(id_form, descricao, arquivo1) values('$texto[id_form]', '$texto[descricao]', '$uploadedFile')";
	$stmt=$pdo->prepare($sql);
	$stmt->execute(); 
	
	return $uploadedFile;


});


$app->post('/', function(Request $request, Response $response) {
    $directory = $this->get('upload_directory');

    $uploadedFiles = $request->getUploadedFiles();

    // handle single input with single file upload
    $uploadedFile = $uploadedFiles['example1'];
    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);
        $response->write('uploaded ' . $filename . '<br/>');
    }


    // handle multiple inputs with the same key
    foreach ($uploadedFiles['example2'] as $uploadedFile) {
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $uploadedFile);
            $response->write('uploaded ' . $filename . '<br/>');
        }
    }

    // handle single input with multiple file uploads
    foreach ($uploadedFiles['example3'] as $uploadedFile) {
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $uploadedFile);
            $response->write('uploaded ' . $filename . '<br/>');
        }
    }

});

/**
 * Moves the uploaded file to the upload directory and assigns it a unique name
 * to avoid overwriting an existing uploaded file.
 *
 * @param string $directory directory to which the file is moved
 * @param UploadedFile $uploaded file uploaded file to move
 * @return string filename of moved file
 */
function moveUploadedFile($directory, UploadedFile $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

$app->run();
