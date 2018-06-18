<?php

require 'smtp.php';

$idInicial = '';
$idFinal = '';
$campoIndex = 0;
$campoName = '';
$maxpages = 2;

if(array_key_exists("idInicial", $_POST)){
    $idInicial = $_POST["idInicial"];
    $idFinal = $_POST["idFinal"];
    $campoIndex = $_POST["campo"];
}

$config = parse_ini_file("dispatcher.ini", true);

// Configuracao da base destino para log MySQL
$dbLogConnectionConfig = $config["DBLogConnectionConfig"];    
$dbLogServer = $dbLogConnectionConfig["DBServer"];
$dbLogUser = $dbLogConnectionConfig["DBUser"];
$dbLogPassword = $dbLogConnectionConfig["DBPassword"];
$dbLogDatabase = $dbLogConnectionConfig["DBDatabase"];  

// MySQLFieldsDescription
$mySQLFields = $config["MySQLFieldsDescription"];

// Configuracao do smtp
$SMTPConfig = $config["SMTPConfig"]; 
$smtp = new SMTP();        
$smtp->userName = $SMTPConfig['userName'];
$smtp->password = $SMTPConfig['password'];
$smtp->server = $SMTPConfig['server'];
$smtp->port = $SMTPConfig['port'];
$smtp->SMTPSecure = $SMTPConfig['SMTPSecure'];
$smtp->from = $SMTPConfig["from"];
$smtp->fromName = $SMTPConfig["fromName"];

// General Config
$GeneralConfig = $config["GeneralConfig"];
$emailAdm = $GeneralConfig["emailAdm"];
$emailAdmName = $GeneralConfig["emailAdmName"];
$emailAdmSubject = $GeneralConfig["emailAdmSubject"];
$emailAdmMessage = $GeneralConfig["emailAdmMessage"];

$logConn = mysqli_connect($dbLogServer, $dbLogUser, $dbLogPassword, $dbLogDatabase);
if (mysqli_connect_errno()) {
    //$debug(LogLevel::Error, "DBLogConnect - MySQL Connect Log failed: ".mysqli_connect_error());
    return false;
}      

$select = 'select * from log where DATE_FORMAT(dataEmail, "%d/%m/%Y") = DATE_FORMAT(curdate(), "%d/%m/%Y")';
//$select = 'select * from log ';

$logQuery = mysqli_query($logConn, $select);
if (!$logQuery) {
    //printf("Error: %s\n", mysqli_error($logConn));
    return false;
}    

$num_rows = mysqli_num_rows($logQuery);
if($num_rows == 0){
    echo "Não houve emails ontem transmitidos";
    mysqli_close($logConn); 
    return false;  
}

$fieldCount = mysqli_num_fields( $logQuery );

$result = '<table><tr>';

for($i = 0; $i < $fieldCount; $i++){    
    $fieldName = mysqli_fetch_field_direct($logQuery, $i)->name;
    if(array_key_exists($fieldName, $mySQLFields)){
        $fieldName = $mySQLFields[$fieldName];
    }    
    $result .= '<th>'.$fieldName.'</th>';
}

$result .= '</tr>';

$fileName = "planilha.xls";
unlink($fileName); // apaga a planilha antes de gerar outra 

error_log($result, 3, $fileName);
$result = '';

while($logDataset = mysqli_fetch_array($logQuery, MYSQLI_BOTH)){    
    $result .= '<tr>';
    for($i = 0; $i < $fieldCount; $i++){    
        if($i == 0){
            $result .= '<th>'.$logDataset[$i].'</th>';
        } else {
            $result .= '<td>'.$logDataset[$i].'</td>';
        }
    }
    $result .= '</tr>';

    error_log($result, 3, $fileName);
    $result = '';
}   
$result .= '</table>';

error_log($result, 3, $fileName);
$result = '';

mysqli_close($logConn);

if($smtp->connect()){        
    $address = $emailAdm;
    $name = $emailAdmName;
    $subject = $emailAdmSubject;
    $email = $result;
    if($smtp->send($address, $name, $subject, $emailAdmMessage, $fileName)){
        return true;
    }
    return false;
} else {
    echo "Erro no smtp";
}
?>