<?php

if(!($serverInfos['deleted_at'] == NULL)){
    header('Location: '.$helper->url().'order/rootserver');
    die();
}

if(!is_null($serverInfos['locked'])){
    $_SESSION['product_locked_msg'] = $serverInfos['locked'];
    header('Location: '.env('URL').'manage/rootserver');
    die();
}

if(is_null($serverInfos['traffic'])){
    $available_traffic = $helper->getSetting('default_traffic_limit');
} else {
    $available_traffic = $serverInfos['traffic'];
}

if(isset($_POST['buyTraffic'])){
    $error = null;

    $traffic_valid = false;
    $traffic_amount = $_POST['traffic_amount'];
    if($traffic_amount == '512' || $traffic_amount == '1024'){
        $traffic_valid = true;
    }
    if($traffic_valid == false){
        $error = 'Diese möglichkeit existiert nicht';
    }


    if($traffic_amount == '512'){
        $price = '7.00';
    }
    if($traffic_amount == '1024'){
        $price = '14.00';
    }

    if($price > $amount){
        $error = 'Du hast nicht genügent Guthaben';
    }

    if(empty($error)){

        $user->removeMoney($price, $userid);
        $user->addTransaction($userid, $price,'KVM #'.$id.' | Extra Traffic '.$traffic_amount.'GB');

        $update = $db->prepare("UPDATE `vm_servers` SET `traffic` = :traffic WHERE `id` = :id");
        $update->execute(array(":traffic" => $available_traffic+$traffic_amount, ":id" => $id));

        $_SESSION['success_msg'] = 'Vielen Dank. Dein Server wird in kürze wieder freigeschaltet!';
        header('Location: '.$site->currentUrl());
        die();

    } else{
        echo sendError($error);
    }
}

if($serverInfos['state'] == 'SUSPENDED'){
    $suspended = true;
    die(header('Location: '.$helper->url().'renew/rootserver/'.$id));
} else {
    $suspended = false;
}

if($userid != $serverInfos['user_id']){
    die(header('Location: '.$helper->url().'manage/rootserver'));
}

$venocix_id = json_decode($serverInfos['venocix_id']);
$vm_id = $venocix_id->result->output->vmid;
$status = $venocix->currentVMStatus($vm_id);
if($status->result->state == 'running'){
    $state = '<span class="badge badge-success">Online</span>';
    $serverStatus = 'ONLINE';
} else {
    $serverStatus = 'OFFLINE';
    $state = '<span class="badge badge-danger">Offline</span>';
}

if (isset($_POST['sendStop'])) {
    $error = null;

    if ($status->result->state == 'stopped') {
        $error = 'Dein Server ist bereits gestoppt';
    }

    if (empty($error)) {

        $serverStatus = 'OFFLINE';
        $venocix->stop($vm_id);
        echo sendSweetSuccess('Dein Server wird nun gestoppt');

    } else {
        echo sendError($error);
    }
}

if (isset($_POST['sendStart'])) {
    $error = null;

    if ($status->result->state == 'running') {
        $error = 'Dein Server ist bereits gestartet';
    }

    if (empty($error)) {

        $serverStatus = 'ONLINE';
        $venocix->start($vm_id);
        echo sendSweetSuccess('Dein Server wird nun gestartet');

    } else {
        echo sendError($error);
    }
}

if (isset($_POST['sendRestart'])) {
    $error = null;

    if ($status->result->state == 'stopped') {
        $error = 'Dein Server ist bereits gestoppt';
    }

    if (empty($error)) {

        $serverStatus = 'ONLINE';
        $venocix->reboot($vm_id);
        echo sendSweetSuccess('Dein Server wurde nun neugestartet');

    } else {
        echo sendError($error);
    }
}

if (isset($_POST['resetRootPW'])) {
    $error = null;

    if (empty($error)) {

        $response = $venocix->resetRootPW($vm_id);

        $update = $db->prepare("UPDATE `vm_servers` SET `password` = :password WHERE `id` = :id");
        $update->execute(array(":password" => $response->result->password, ":id" => $id));

        echo sendSuccess('Eine Rootpasswort Änderung wurde angefragt');
        header("refresh:2");
    } else {
        echo sendError($error);
    }
}

if($serverStatus == 'ONLINE'){
    $state = '<span class="badge badge-success">Online</span>';
}

if($serverStatus == 'OFFLINE'){
    $state = '<span class="badge badge-danger">Offline</span>';
}

if(isset($_POST['saveRDNS'])){
    $error = null;

    if(empty($_POST['ip_addr'])){
        $error = 'Es wurde keine IP gefunden';
    }

    if(!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP)) {
        $error = 'Es wurde keine gültige IP gefunden';
    }

    if(empty($_POST['rdns'])){
        $error = 'Es wurde kein rDNS eintrag gefunden';
    }

    if(!filter_var($_POST['rdns'], FILTER_VALIDATE_DOMAIN)) {
        $error = 'rDNS Eintgrag ist ungültig';
    }

    $SQL = $db->prepare("SELECT * FROM `ip_addresses` WHERE `service_id` = :service_id AND `ip` = :ip");
    $SQL->execute(array(':service_id' => $id, ':ip' => $_POST['ip_addr']));
    if($SQL->rowCount() != 1){
        $error = 'Keine Rechte auf diese IP Adresse gefunden';
    }

    if(empty($error)){

        $SQL = $db->prepare("UPDATE `ip_addresses` SET `rdns` = :rdns WHERE `service_id` = :service_id AND `ip` = :ip");
        $SQL->execute(array(':rdns' => $_POST['rdns'], ':service_id' => $id, ':ip' => $_POST['ip_addr']));

        $venocix->setRDNS($_POST['ip_addr'], $_POST['rdns']);

        echo sendSuccess('rDNS wurde gespeichert');

    } else {
        echo sendError($error);
    }
}

if(isset($_POST['reinstallServer'])){

    $error = null;

    if(!isset($_POST['serverOS'])){
        $error = "Betriebssystem wurde nicht gefunden!";
    }

    if(is_null($error)){

        $SQL0 = $db->prepare("SELECT * FROM vm_server_os WHERE id = :id");
        $SQL0->execute(array(":id" => $_POST['serverOS']));
        $response = $SQL0->fetch(PDO::FETCH_ASSOC);

        $json = $venocix->reinstallVM($vm_id, $response['template']);

        $SQL = $db->prepare("INSERT INTO `vm_tasks`(`service_id`, `task`) VALUES (:service_id, :task)");
        $SQL->execute(array(":service_id" => $vm_id, ":task" => $json));

        echo sendSuccess('Dein Server wird nun neu installiert. Bitte habe einen Augenblick geduld!');

    } else {
        echo sendError($error);
    }

}

if(isset($_POST['createBackup'])){
    $venocix->createBackup($vm_id);
    echo sendSweetSuccess("Dein Backup wird erstellt");
}

if(isset($_POST['restoreBackup'])){
    $error = null;

    if(!isset($_POST['backupChoice'])){
        $error = "Bitte wähle ein Backup aus!";
    }

    if(is_null($error)){
        $venocix->restoreBackup($vm_id, $_POST['backupChoice']);
        echo sendSweetSuccess("Das Backup wird eingespielt!");
    } else {
        echo sendSweetError($error);
    }

}

if(isset($_POST['installSoftware'])){
    $venocix->installSoftware($vm_id, $serverInfos['password'], $_POST['software']);
    echo sendSweetSuccess("Die Software wird nun installiert");
}
if(isset($_POST['uninstallSoftware'])){
    $venocix->uninstallSoftware($vm_id, $serverInfos['password'], $_POST['software']);
    echo sendSweetSuccess("Die Software wird nun entfernt");
}