<?php
require '../includes/db.php';
require '../includes/auth.php';

$isAdmin = isLoggedIn() && getUserRole() === 'admin';

$stmt=$pdo->query("SELECT * FROM whiteboard_crews ORDER BY crew_name,sort_order");
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

$crews=[];

foreach($data as $row){
    $crews[$row['crew_name']][]=$row;
}
?>

<!doctype html>
<html>
<head>

<title>Truelove Digital Crew Board</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#111;
color:#fff;
}

.crew-box{
background:#1b1b1b;
padding:15px;
border-radius:10px;
min-height:200px;
}

.employee{
padding:6px;
margin:4px;
background:#333;
border-radius:6px;
cursor:grab;
}

.employee:hover{
background:#444;
}

</style>

</head>

<body>

<script>
const isAdmin = <?= $isAdmin ? 'true':'false' ?>;
</script>

<nav class="navbar bg-dark border-bottom border-secondary">

<div class="container">

<span class="navbar-brand text-light">

Truelove Services Crew Board

</span>

</div>

</nav>

<div class="container mt-4">

<div class="row g-4">

<?php

$crewList=['MC1','MC2','MC3','MC4','LC1','TC1','TC2','FC1','TCK1'];

foreach($crewList as $crew):

?>

<div class="col-md-3">

<div class="crew-box">

<h5 class="text-center"><?= $crew ?></h5>

<div class="crew" data-crew="<?= $crew ?>">

<?php
if(isset($crews[$crew])){
foreach($crews[$crew] as $emp){
?>

<div class="employee" data-id="<?= $emp['id'] ?>">

<?= htmlspecialchars($emp['employee_name']) ?>

</div>

<?php }} ?>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>

if(isAdmin){

document.querySelectorAll('.crew').forEach(col=>{

new Sortable(col,{
group:'crews',
animation:150,

onEnd:function(evt){

let empID = evt.item.dataset.id;
let newCrew = evt.to.dataset.crew;

fetch('save_crew_move.php',{

method:'POST',

headers:{
'Content-Type':'application/json'
},

body:JSON.stringify({

employee_id:empID,
crew:newCrew

})

});

}

});

});

}

// auto refresh for TV viewers

if(!isAdmin){

setTimeout(()=>location.reload(),30000);

}

</script>

</body>
</html>