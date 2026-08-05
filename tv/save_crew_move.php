<?php

require 'includes/db.php';
require 'includes/auth.php';

if(!isLoggedIn() || getUserRole() !== 'admin'){
http_response_code(403);
exit;
}

$data=json_decode(file_get_contents("php://input"),true);

$id=$data['employee_id'];
$crew=$data['crew'];

$stmt=$pdo->prepare("UPDATE whiteboard_crews SET crew_name=? WHERE id=?");
$stmt->execute([$crew,$id]);

echo json_encode(['success'=>true]);