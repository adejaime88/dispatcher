<?php

$idInicial = '';
$idFinal = '';
$campoIndex = 0;
$campoName = '';
$campoType = '';
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


$mySQLFields = $config["MySQLFieldsDescription"];

$logConn = mysqli_connect($dbLogServer, $dbLogUser, $dbLogPassword, $dbLogDatabase);
if (mysqli_connect_errno()) {
    //$debug(LogLevel::Error, "DBLogConnect - MySQL Connect Log failed: ".mysqli_connect_error());
    return false;
}      

$select = 'select * from log where 1 = 0';

$logQuery = mysqli_query($logConn, $select);
if (!$logQuery) {
    //printf("Error: %s\n", mysqli_error($logConn));
    return false;
}    

$fieldCount = mysqli_num_fields( $logQuery );

echo '<!doctype html>';
echo '<html>';
echo '<head>';
echo '<meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">';
echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
echo '<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>';
echo '<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>';
echo '<title>Email Dispatcher Viewer</title>';
echo '</head>';
echo '<body>';
echo '<div class="page-header mb-5">';
echo '  <h1 style="text-align:center;">Consulta de Emails Gerados</h1>';
echo '</div>';
echo '<form class="form-horizontal" action="consulta.php" method="post">';
echo '  <div class="form-group">';
echo '   <div class="input-group mb-3 col-sm-5">';
echo '    <div class="input-group-prepend">';
echo '     <span class="input-group-text" id="basic-addon1">Pesquisar por:</span>';
echo '    </div>';
echo '    <select name="campo" class="form-control>" placeholder="Pesquisar por" aria-label="campo" aria-describedby="basic-addon1">';
for($i = 0; $i < $fieldCount; $i++){        
    $fieldName = mysqli_fetch_field_direct($logQuery, $i)->name;
    if(array_key_exists($fieldName, $mySQLFields)){
        $fieldName = $mySQLFields[$fieldName];
    }        

    if($i == $campoIndex){
        echo '<option selected value="'.$i.'">'.$fieldName.'</option>';
        $campoName = mysqli_fetch_field_direct($logQuery, $i)->name;
        $campoType = mysqli_fetch_field_direct($logQuery, $i)->type;
    } else {
        echo '<option value="'.$i.'">'.$fieldName.'</option>';
    }
}
echo '    </select>';
echo '   </div>';
echo '  </div>';
echo '  <div class="form-group">';
echo '   <div class="input-group mb-3 col-sm-5">';
echo '    <div class="input-group-prepend">';
echo '     <span class="input-group-text" id="basic-addon1">Filtro Inicial</span>';
echo '    </div>';
echo '    <input type="text" class="form-control" placeholder="Filtro Inicial" aria-label="idInicial" aria-describedby="basic-addon1" name="idInicial" value="'.$idInicial.'">';
echo '   </div>';
echo '  </div>';
echo '  <div class="form-group">';
echo '   <div class="input-group mb-3 col-sm-5">';
echo '    <div class="input-group-prepend">';
echo '     <span class="input-group-text" id="basic-addon1">Filtro Final</span>';
echo '    </div>';
echo '    <input type="text" class="form-control" placeholder="Filtro Final" aria-label="idFinal" aria-describedby="basic-addon1" name="idFinal" value="'.$idFinal.'">';
echo '   </div>';
echo '  </div>';
echo '  <div class="form-group">';
echo '   <div class="input-group mb-3 col-sm-5">';
echo '    <button type="submit" name="submit" class="btn btn-primary">Pesquisar</button>';
echo '   </div>';
echo '  </div>';
echo '</form>';
$result = '<div class="">';
$result .= '<table class="table table-striped table-bordered table-hover"><thead><tr>';

for($i = 0; $i < $fieldCount; $i++){    
    $fieldName = mysqli_fetch_field_direct($logQuery, $i)->name;
    if(array_key_exists($fieldName, $mySQLFields)){
        $fieldName = $mySQLFields[$fieldName];
    }    
    $result .= '<th>'.$fieldName.'</th>';
}

$result .= '</tr></thead><tbody>';

$page = 1;
if(array_key_exists("p", $_GET)){
    $page = $_GET["p"];
}

$rowInit = $page * 20 - 20;

$select = 'select * from log';
if($idInicial != '' && $idFinal != ''){
    if($campoType == 253 /*varchar*/){
        $select .= " where ".$campoName." like '%".$idInicial."%' and ".$campoName." like '%".$idFinal."%'";
    } else {
        $select .= " where ".$campoName." >= '".$idInicial."' and ".$campoName." <= '".$idFinal."'";
    }
} else if($idInicial != ''){
    if($campoType == 253 /*varchar*/){
        $select .= " where ".$campoName." like '%".$idInicial."%'";
    } else {
        $select .= " where ".$campoName." >= '".$idInicial."'";
    }
} else if($idFinal != ''){
    if($campoType == 253 /*varchar*/){
        $select .= " where ".$campoName." like '%".$idFinal."%'";
    } else {
        $select .= " where ".$campoName." <= '".$idFinal."'";
    }

}


//echo $select;

$logQuery = mysqli_query($logConn, $select);
if (!$logQuery) {
    //printf("Error: %s\n", mysqli_error($logConn));
    return false;
}   

$num_rows = mysqli_num_rows($logQuery);

$pageCount = intdiv($num_rows, 20);

if($num_rows % 20 > 0){
    $pageCount++;
}

$select .= " LIMIT " . $rowInit . ", 20";

$logQuery = mysqli_query($logConn, $select);
if (!$logQuery) {
    //printf("Error: %s\n", mysqli_error($logConn));
    return false;
}

$navpage = '<nav aria-label="...">';
$navpage .= '<ul class="pagination justify-content-center">';

if($page > $maxpages){
    $priorp = intdiv($page, $maxpages);
    if($page % $maxpages > 0){
        $priorp++;
    }
    $priorp--;
    $priorp = $priorp * $maxpages;

    $navpage .= '<li class="page-item">';
    $navpage .= '      <a class="page-link" href="consulta.php?p='.$priorp.'" tabindex="-1">Anterior</a>';
    $navpage .= '</li>'; 
} else {
    $navpage .= '<li class="page-item disabled">';
    $navpage .= '      <a class="page-link" href="#" tabindex="-1">Anterior</a>';
    $navpage .= '</li>';
}


$pgInit = intdiv($page, $maxpages);
if($page % $maxpages > 0){
    $pgInit++;
}

if($pgInit <= 0){
    $pgInit = 1;
}
if($pgInit > 1){
    $pgInit = $pgInit * $maxpages - 1;    
}

$pgCount = 1;
for($i = $pgInit; $i <= $pageCount; $i++){    
    if($pgCount > $maxpages){
        $i--;
        break;
    }

    if($i == $page){        
        $navpage .= '<li class="page-item active">';
        $navpage .= '      <a class="page-link" href="#">'.$i.'<span class="sr-only">(current)</span></a>';
        $navpage .= '</li>';
    } else {
        $navpage .= '<li class="page-item"><a class="page-link" href="consulta.php?p='.$i.'">'.$i.'</a></li>';
    }
    $pgCount++;
}

if($pageCount > $i){
    $nextp = $pgInit + $maxpages;
    $navpage .= '<li class="page-item">';
    $navpage .= '      <a class="page-link" href="consulta.php?p='.$nextp.'">Próxima</a>';
    $navpage .= '</li>';               
}
$navpage .= '</ul>';
$navpage .= '</nav>'; 

echo $navpage;


$row = 0;
while($logDataset = mysqli_fetch_array($logQuery, MYSQLI_BOTH)){ // teste somente processa um email    
    $result .= '<tr>';
    for($i = 0; $i < $fieldCount; $i++){    
        if($i == 0){
            $result .= '<th>'.$logDataset[$i].'</th>';
        } else {
            $result .= '<td>'.$logDataset[$i].'</td>';
        }
    }
    $result .= '</tr>';
    $row++;
    if($row >= 20){
        break;
    }
}   
$result .= '</tbody></table></div>';

$result .= '</body>';
$result .= '</html>';

mysqli_close($logConn);
echo $result;
?>