<?php

/*
dispacher.php - dispatcher de emails
autor: Julio Cesar Fernandes de Souza
data: mar/2018
email: jcfsouza@yahoo.com.br
*/

require 'smtp.php';

abstract class LogLevel
{
    const Error = 0;
    const Warning = 1;
    const Info = 2;
}

 class EmailTemplate {

    private $content = "";
    private $subject = "";
    private $config = "";
    private $configFile = "";
    private $templateHTMLFile = "";
    
    private $sql = "";
    private $emailKey = null;
    private $sqlKey = null;
    private $emailKeys = null;
    private $emailSQLField = null;
    private $nomeEmailSQLField = null;
    private $emailValues = null;
    private $logKeys = null;    

    public function __construct($configFile)
    {        
        $this->configFile = $configFile;        
        $this->config = parse_ini_file($this->configFile, true);
        $emailConfig = $this->config["EmailConfig"];
    
        $this->subject = $emailConfig["emailSubject"];
        $this->templateHTMLFile = $emailConfig["templateHTMLFile"];
        $this->emailSQLField = $emailConfig["emailSQLField"];
        $this->nomeEmailSQLField = $emailConfig["nomeEmailSQLField"];
        $this->sql = $emailConfig["sql"];
        $this->emailKeys = $this->config["emailKeys"];

        // Configuracao dos campos do log
        $this->logKeys = $this->config["logKeys"];    

    }   

    function __destruct() {
    }    

    public function init(){
        $this->content = file_get_contents($this->GetTemplateFile()); 
    }

    public function getEmail(){
        $this->init();

        $search = array();
        $replace = array();

        $i = 0;        
        while(true){
            // procura pela chave emailKey(n)
            $key = "emailKey".($i + 1);            
            if(!array_key_exists($key, $this->emailKeys)){
                break;                                                
            }

            // pega a chave
            $emailKey = $this->emailKeys[$key];

            // procura pelo campo da base
            $sqlK = "sqlKey".($i + 1);
            $sqlKey = $this->emailKeys[$sqlK];

            // verifica se o campo da base está em emailValues
            if(array_key_exists($sqlKey, $this->emailValues)){
                $search[$i] = $emailKey;        
                $replace[$i] = $this->emailValues[$sqlKey];
            }    
            $i++;
        }    
        
        $email = str_replace($search, $replace, $this->content);
        return $email;
    }

    public function SetKeyValue($sqlKey, $sqlValue){
        $this->emailValues[$sqlKey] = $sqlValue;    
    }
    
    protected function GetTemplateFile(){
        return $this->templateHTMLFile;
    
    }

    public function GetSQLSelect(){
        return $this->sql;
    }

    public function GetKeys(){
        return $this->emailKeys;
    }

    public function GetSubject(){
        return $this->subject;
    }

    public function GetLogKeys(){
        return $this->logKeys;
    }

    public function GetEmailFieldName(){
        return $this->emailSQLField;
    }

    public function GetNomeEmailFieldName(){
        return $this->nomeEmailSQLField;
    }
 }

 // Dispatcher - classe principal de gerenciamento de emails
 class Dispatcher {

    private $emailTemplate = null;
    private $countTeste = 0;
    private $query = null;
    private $conn = null;
    private $stid = null;
    private $smtp = null;
    private $configFile = null;
    private $emailConfig = null;
    private $dbServer = null;
    private $dbUser = null;
    private $dbPassword = null;
    private $dbDatabase = null;
    private $dbConnectionString = null;
    private $emailTest = null;
    private $logQuery = null;
    private $logConn = null;
    private $logStmt = null;
    private $logStmtNewId = null;
    private $dbLogServer = null;
    private $dbLogUser = null;
    private $dbLogPassword = null;
    private $dbLogDatabase = null;   
    private $logDataset = null; 
    private $logLevel = null;

    public function __construct($configFile)
    {        
        $this->configFile = $configFile;
        $this->config = parse_ini_file("dispatcher.ini", true);

        // Configuracao da base origem dos dados Oracle
        $dbConnectionConfig = $this->config["DBConnectionConfig"];    
        $this->dbServer = $dbConnectionConfig["DBServer"];
        $this->dbUser = $dbConnectionConfig["DBUser"];
        $this->dbPassword = $dbConnectionConfig["DBPassword"];
        $this->dbDatabase = $dbConnectionConfig["DBDatabase"];
        $this->dbConnectionString = $dbConnectionConfig["DBConnectionString"];
        
        $this->emailTemplate = new EmailTemplate($this->configFile);

        // Configuracao da base destino para log MySQL
        $dbLogConnectionConfig = $this->config["DBLogConnectionConfig"];    
        $this->dbLogServer = $dbLogConnectionConfig["DBServer"];
        $this->dbLogUser = $dbLogConnectionConfig["DBUser"];
        $this->dbLogPassword = $dbLogConnectionConfig["DBPassword"];
        $this->dbLogDatabase = $dbLogConnectionConfig["DBDatabase"];      

        // Configuracao do smtp
        $SMTPConfig = $this->config["SMTPConfig"]; 
        $this->smtp = new SMTP();        
        $this->smtp->userName = $SMTPConfig['userName'];
        $this->smtp->password = $SMTPConfig['password'];
        $this->smtp->server = $SMTPConfig['server'];
        $this->smtp->port = $SMTPConfig['port'];
        $this->smtp->SMTPSecure = $SMTPConfig['SMTPSecure'];
        $this->smtp->from = $SMTPConfig["from"];
        $this->smtp->fromName = $SMTPConfig["fromName"];

        if(array_key_exists("emailTest", $SMTPConfig)){
            $this->emailTest = $SMTPConfig["emailTest"];
        } else {
            $this->emailTest = null;
        }

        // General Config
        $GeneralConfig = $this->config["GeneralConfig"];
        $this->logLevel = $GeneralConfig["logLevel"];
    }     

    function __destruct() {
        $this->emailTemplate = null;
        $this->smtp = null;
    }             

    function debug( $logLevel, $data ) {
        try {
            if($logLevel < $this->logLevel){
                $LogLevelStr = ["Error", "Warning", "Info"];
                $date = new DateTime();
                $log = date_format($date, "Y-m-d H:i:s.u")." - ".$LogLevelStr[$logLevel]." - ".$data."\r\n";
                echo $log;
                error_log($log, 3, "log.txt");
            }
        } catch(Exception $e){
        }
    }

    private function DBLogConnect(){        
        $this->debug(LogLevel::Info, "DBLogConnect - in");
        try{
            try{                
                $this->logConn = mysqli_connect($this->dbLogServer, $this->dbLogUser, $this->dbLogPassword, $this->dbLogDatabase);
                if (mysqli_connect_errno()) {
                    $this->debug(LogLevel::Error, "DBLogConnect - MySQL Connect Log failed: ".mysqli_connect_error());
                    return false;
                }      
                mysqli_autocommit($this->logConn, FALSE);
                $this->logQuery = "UPDATE Log SET originalId = ?, cliente = ?, tipoEmail = ?, email = ?, dataVenc = ?, valor = ?, cpfcnpj = ?, nota = ?, cheque = ?, codbarras = ?, emailTransmitido = ?, emailObs = ?, dataEmail = ?, chaveRegistro = ? WHERE id = ?";

                $this->logStmt = mysqli_prepare($this->logConn, $this->logQuery) or die(mysqli_error($this->logConn)); 
            
                $this->logStmtNewId = mysqli_prepare($this->logConn, 'insert into log (id) select IfNull(max(log2.id), 0)+1 from log log2') or die(mysqli_error($this->logConn)); 
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBLogConnect - MySQL Error: ".$e->getMessage()); 
                return false;
            }
        } finally {   
            $this->debug(LogLevel::Info, "DBLogConnect - out");
        }
        return true;
    }

    private function DBConnect(){        
        $this->debug(LogLevel::Info, "DBConnect - in");
        try {
            try {        
                $this->conn = oci_connect($this->dbUser, $this->dbPassword, $this->dbConnectionString);
                if (!$this->conn) {
                    $e = oci_error();
                    $msg = $e['message'];
                    $this->debug(LogLevel::Error, "DBConnect - Oracle Connect failed: ".$msg);
                    return false;
                }    
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBConnect - Oracle Error: ".$e->getMessage()); 
                return false;
            }            
        } finally {
            $this->debug(LogLevel::Info, "DBConnect - out");    
        }    
        return true;
    }
 
    private function DBLogDisconnect(){
        $this->debug(LogLevel::Info, "DBLogDisconnect - in");
        try {
            try {
                if($this->logConn){
                    mysqli_close($this->logConn);
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBLogDisconnect - MYSQL Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBLogDisconnect - out");
        }
        return true;
    }
    
    private function DBDisconnect(){
        $this->debug(LogLevel::Info, "DBDisconnect - in");
        try {
            try {
                if($this->stid){
                    oci_free_statement($this->stid);
                }
                if($this->conn){
                    oci_close($this->conn);
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBDisconnect - Oracle Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBDisconnect - out"); 
        }
        return true;
    }

    private function GetEmailTemplate(){    
        return $this->emailTemplate;
    }

    private function MakeEmail(){
        $this->debug(LogLevel::Info, "MakeEmail - in");
        try {
            try {
                $template = $this->GetEmailTemplate();
                $emailKeys = $template->GetKeys();

                $i = 1;
                while(true){
                    if(!array_key_exists("sqlKey$i", $emailKeys)){
                        break;    
                    }
                    $sqlKey = $emailKeys["sqlKey$i"];
                    $sqlvalue = $this->dataset[$sqlKey];
                    $template->SetKeyValue($sqlKey, $sqlvalue); 
                    $i++; 
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "MakeEmail - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "MakeEmail - out");
        }
        return true;
    }

    private function SendEmail(){
        $this->debug(LogLevel::Info, "SendEmail - in");
        try {
            try {
                $template = $this->GetEmailTemplate();
                $address = $this->dataset[$template->GetEmailFieldName()];
                if($this->emailTest && $this->emailTest != ""){
                    $address = $this->emailTest;
                }

                $name = $this->dataset[$template->GetNomeEmailFieldName()];
                $subject = $template->getSubject();
                $email = $template->getEmail();

                /* grava em arquivo
                $name = 'email.html';
                $file = fopen($name, 'a');
                fwrite($file, $email);
                fclose($file);*/

            //  $this->debug("email sent teste");
            //  return true;

                if($this->smtp->connect()){
                    if($this->smtp->send($address, $name, $subject, $email, null)){
                        return true;
                    }
                    return false;
                } else {
                    return false;
                }    
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "SendEmail - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "SendEmail - out");    
        }

        return true;
    }

    // Verifica se o email já foi transmitido
    private function VerifyEmail(){        
        $this->debug(LogLevel::Info, "VerifyEmail - in");
        try {
            try {
                $template = $this->GetEmailTemplate();
                $emailKeys = $template->GetKeys();
                $logKeys = $template->GetLogKeys();

                $tipoEmail = trim($logKeys["tipoEmail"]);

                $sqlOriginalId = trim($logKeys["originalId"]);
                if($sqlOriginalId != ''){
                    $originalId = $this->dataset[$sqlOriginalId];
                } else {
                    $originalId = '';
                }    
                
                $sqlChaveRegistro = trim($logKeys["chaveRegistro"]);
                if($sqlChaveRegistro != ''){
                    $chaveRegistro = $this->dataset[$sqlChaveRegistro];
                } else {
                    $chaveRegistro = '';
                }

                $originalIdValue = $this->dataset[$sqlOriginalId];

                $select = 'select 1 from log where tipoEmail = "'.$tipoEmail.'" and originalId = "'.$originalIdValue.'" and chaveRegistro = "'.$chaveRegistro.'"';

                $this->debug(LogLevel::Info, "VerifyEmail - Select: ".$select);

                $logQuery = mysqli_query($this->logConn, $select);

                $num_rows = mysqli_num_rows($logQuery);

                if($num_rows > 0){
                    // email já foi gerado
                    $this->debug(LogLevel::Info, "VerifyEmail - Email já processado");
                    return false;
                }
                $this->debug(LogLevel::Info, "VerifyEmail - Email ainda não processado");
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "VerifyEmail - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "VerifyEmail - out");
        }
        return true;        
    }

    private function ProcessEmail(){
        if(!$this->VerifyEmail()){
            return false;
        }
        if(!$this->MakeEmail()){
            return false;    
        }
        if(!$this->SendEmail()){
            return false;
        }
        return true;
    }

    private function DBGetSelect(){
        $template = $this->GetEmailTemplate();
        $select = $template->GetSQLSelect();
        return $select;
    }

    private function DBOpen(){
        $this->debug(LogLevel::Info, "DBOpen - in");
        try {
            try {
                $select = $this->DBGetSelect();

                $this->stid = oci_parse($this->conn, $select);
                if (!$this->stid) {
                    $e = oci_error($this->conn);
                    printf("Error: %s\n", $e['message']);
                }
                
                // Perform the logic of the query
                $this->query = oci_execute($this->stid);
                if (!$this->query) {
                    $e = oci_error($this->stid);
                    printf("Error: %s\n", $e['message']);
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBOpen - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBOpen - out");    
        }

        return true;
    }

    private function DBFirst(){
        $this->debug(LogLevel::Info, "DBFirst - in");
        try {
            try {
                $this->dataset = oci_fetch_array($this->stid, OCI_ASSOC + OCI_RETURN_NULLS); 
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBFirst - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBFirst - out");
        }
        return true;
    }

    private function DBNext(){
        $this->debug(LogLevel::Info, "DBNext - in");
        try {
            try {
                $this->dataset = oci_fetch_array($this->stid, OCI_ASSOC + OCI_RETURN_NULLS); 
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBNext - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBNext - out");
        }
        return true;
    }

    private function DBEof(){
        return $this->dataset == null;    
    }

    private function DBLogGetNextId(){
        $this->debug(LogLevel::Info, "DBLogGetNextId - in");
        try {
            mysqli_begin_transaction($this->logConn);
            try {            
                if(!mysqli_stmt_execute($this->logStmtNewId)){  
                    $this->debug(LogLevel::Error, "log insert error: " + mysqli_error($this->logConn));
                    return -1;
                }            
                $this->logQuery = mysqli_query($this->logConn, 'select max(id) as maxid from log');
                if (!$this->logQuery) {
                    printf("Error: %s\n", mysqli_error($this->logConn));
                    return false;
                }    
                $this->logDataset = mysqli_fetch_array($this->logQuery, MYSQLI_BOTH);
                $result = $this->logDataset["maxid"];
                mysqli_commit($this->logConn);
                return $result;
            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "DBLogGetNextId - Error: ".$e->getMessage()); 
                mysqli_rollback($this->logConn);                
                return -1;
            }       
        } finally {
            $this->debug(LogLevel::Info, "DBLogGetNextId - out");    
        }     
    }

    private function DBLogUpdate(){              
        $this->debug(LogLevel::Info, "DBUpdate - in");        
        try {
            try {                                                             
                if(!mysqli_stmt_bind_param($this->logStmt, 'isssssssssisssi', $originalId, $cliente, $tipoEmail, $email, $dataVenc, $valor, $cpfcnpj, $nota, $cheque, $codbarras, $emailTransmitido, $emailObs, $dataEmail, $chaveRegistro, $id)){
                    return false;
                }    

                $template = $this->GetEmailTemplate();
                $logKeys = $template->GetLogKeys();
                $id = $this->DBLogGetNextId();
                if($id < 0){
                    return false;
                }
                $sqlOriginalId = trim($logKeys["originalId"]);
                if($sqlOriginalId != ''){
                    $originalId = $this->dataset[$sqlOriginalId];
                } else {
                    $originalId = '';
                }

                $sqlCliente = trim($logKeys["cliente"]);
                if($sqlCliente != ''){
                    $cliente = $this->dataset[$sqlCliente];
                } else {
                    $cliente = '';
                }

                $tipoEmail = trim($logKeys["tipoEmail"]);
                $dataEmail = date("Y-m-d H:i:s");

                $sqlEmail = trim($logKeys["email"]);
                if($sqlEmail != ''){
                    $email = $this->dataset[$sqlEmail];
                } else {
                    $email = '';
                }

                $sqlDataVenc = trim($logKeys["dataVenc"]);
                if($sqlDataVenc != ''){
                    $dataVenc = $this->dataset[$sqlDataVenc];
                } else {
                    $dataVenc = '';
                }

                if($dataVenc != ''){
                    $dt = DateTime::createFromFormat('d/m/y', $dataVenc);
                    if(!$dt){
                        $dt = DateTime::createFromFormat('d/m/Y', $dataVenc);
                    }
                    if($dt){
                        $dataVenc = $dt->format('Y-m-d');
                    } else {
                        $dataVenc = '';
                        $erro = $erro.' - dataVenc com formato inválido';
                    }
                }                

                $sqlValor = trim($logKeys["valor"]);
                if($sqlValor != ''){
                    $valor = (float) $this->dataset[$sqlValor];
                } else {
                    $valor = 0.0;
                }
                $sqlCpfcnpj = trim($logKeys["cpfcnpj"]);
                if($sqlCpfcnpj != ''){
                    $cpfcnpj = $this->dataset[$sqlCpfcnpj]; 
                } else {
                    $cpfcnpj = '';
                }
                $sqlNota = trim($logKeys["nota"]);
                if($sqlNota != ''){
                    $nota = $this->dataset[$sqlNota];
                } else {
                    $nota = '';
                }
                $sqlCheque = trim($logKeys["cheque"]);
                if($sqlCheque != ''){
                    $cheque = $this->dataset[$sqlCheque];
                } else {
                    $cheque = '';
                }
                $sqlCodBarras = trim($logKeys["codbarras"]);
                if($sqlCodBarras != ''){
                    $codbarras = $this->dataset[$sqlCodBarras];
                } else {
                    $codbarras = '';
                }
                $emailTransmitido = 1;
                $emailObs = '';
                
                $sqlChaveRegistro = trim($logKeys["chaveRegistro"]);
                if($sqlChaveRegistro != ''){
                    $chaveRegistro = $this->dataset[$sqlChaveRegistro];
                } else {
                    $chaveRegistro = '';
                }

                $this->debug(LogLevel::Info, "DBUpdate - originalId: ".$originalId.
                    " - cliente: ".$cliente.
                    " - tipoEmail: ".$tipoEmail.
                    " - dataEmail: ".$dataEmail.
                    " - Email: ".$email.
                    " - dataVenc: ".$dataVenc.
                    " - valor: ".$valor.
                    " - cpfcnpj: ".$cpfcnpj.
                    " - nota: ".$nota.
                    " - cheque: ".$cheque.
                    " - codbarras: ".$codbarras.
                    " - emailTransmitido: ".$emailTransmitido.
                    " - emailObs: ".$emailObs.
                    " - chaveRegistro: ".$chaveRegistro);

                if(!mysqli_stmt_execute($this->logStmt)){
                    $error = mysqli_error($this->logStmt);
                    $this->debug(LogLevel::Error, "DBUpdate - log insert error: ".$error);
                    return false;
                }

                mysqli_commit($this->logConn);

            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "DBUpdate - Error: ".$e->getMessage());               
                return false;
            }  
        } finally {
            $this->debug(LogLevel::Info, "DBUpdate - out");     
        }
        return true;
    }

    public function execute(): void{
        $this->debug(LogLevel::Info, "execute - in");
        try {                    
            try {
                if(!$this->DBConnect()){
                    return;
                }
                if(!$this->DBLogConnect()){                    
                    return;
                }
                if(!$this->DBOpen()){
                    return;
                }
                $countTeste = 0;
                $this->DBFirst();
                while(!$this->DBEof() && $countTeste < 1000){ // teste somente processa um email
                    $this->debug(LogLevel::Info, "execution count: $countTeste");
                    if($this->ProcessEmail()){
                        $this->DBLogUpdate();
                    }
                    $this->DBNext();
                    $countTeste++;
                }      
            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "execute - Error: ".$e->getMessage());               
            }       
        } finally {            
            $this->DBDisconnect();
            $this->DBLogDisconnect();
            $this->debug(LogLevel::Info, "execute - out");
        }
     }
 }

  date_default_timezone_set("America/Sao_Paulo");
  
  if(count($argv) < 2){      
      throw new Exception("Invalid Parameters: Syntax: dispatcher.php <config.ini>");
  } 

  $disp = new Dispatcher($argv[1]);
  $disp->execute();

  $disp = null;

?>