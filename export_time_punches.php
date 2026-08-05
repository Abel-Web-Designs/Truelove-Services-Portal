<?php
require 'includes/db.php';
require 'includes/auth.php';

requireLogin();

if (getUserRole() !== 'admin') {
    die('Access denied.');
}

date_default_timezone_set('America/Indiana/Indianapolis');

require_once 'includes/fpdf/fpdf.php';

$startDate = $_GET['start_date'] ?? null;

if (!$startDate) {
    die("Start date is required.");
}

$startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
if (!$startDateObj) {
    die("Invalid start date format.");
}

$startDateObj->setTime(0,0,0);

$days = [];
for ($i=0;$i<7;$i++){
    $d=(clone $startDateObj)->modify("+$i day");
    $days[]=$d;
}

$rangeStart=(clone $startDateObj);
$rangeEnd=(clone $startDateObj)->modify("+6 day")->setTime(23,59,59);

$fetchStart=(clone $rangeStart)->modify("-1 day");
$fetchEnd=(clone $rangeEnd)->modify("+1 day");

$employees=$pdo->query("
SELECT id,name,role
FROM employees
WHERE is_active=1 AND role<>'time_station'
ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare("
SELECT
t.employee_id,
t.timestamp,
LOWER(TRIM(t.clock_type)) AS clock_type
FROM time_logs t
WHERE t.timestamp BETWEEN ? AND ?
ORDER BY t.employee_id,t.timestamp
");

$stmt->execute([
$fetchStart->format('Y-m-d H:i:s'),
$fetchEnd->format('Y-m-d H:i:s')
]);

$logs=$stmt->fetchAll(PDO::FETCH_ASSOC);

$dayKeys=[];
foreach($days as $d){
$dayKeys[$d->format('Y-m-d')]=true;
}

$secondsByEmpByDay=[];
$secondsByEmpWeek=[];
$intervalsByEmp=[];
$lastInByEmp=[];

foreach($logs as $log){

$empId=(int)$log['employee_id'];
$type=$log['clock_type'];
$ts=new DateTime($log['timestamp']);

if($type==='in'){
if(!isset($lastInByEmp[$empId])){
$lastInByEmp[$empId]=$ts;
}
continue;
}

if($type==='out' && isset($lastInByEmp[$empId])){

$in=$lastInByEmp[$empId];
$out=$ts;

$diff=$out->getTimestamp()-$in->getTimestamp();

if($diff>0){

$inDateKey=$in->format('Y-m-d');

if(isset($dayKeys[$inDateKey])){

$secondsByEmpByDay[$empId][$inDateKey]=
($secondsByEmpByDay[$empId][$inDateKey]??0)+$diff;

$secondsByEmpWeek[$empId]=
($secondsByEmpWeek[$empId]??0)+$diff;

$intervalsByEmp[$empId][] = [
'in' => $in,
'out' => $out,
'duration' => $diff,
'date' => $inDateKey,
];

}

}

unset($lastInByEmp[$empId]);

}

}

$attendance=[];

try{

$stmt=$pdo->prepare("
SELECT employee_id,attendance_date,present
FROM daily_attendance
WHERE attendance_date BETWEEN ? AND ?
");

$stmt->execute([
$rangeStart->format('Y-m-d'),
$rangeEnd->format('Y-m-d')
]);

foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){

$eid=(int)$row['employee_id'];
$d=$row['attendance_date'];

$attendance[$eid][$d]=(int)$row['present'];

}

}catch(Throwable $e){}

function pdfText($s){
$converted=@iconv('UTF-8','ISO-8859-1//TRANSLIT',$s);
return $converted!==false?$converted:$s;
}

function calculateAdditionalSeconds(array $intervals, float $thresholdHours = 42.5): int {
    $thresholdSeconds = (int) round($thresholdHours * 3600);
    $cumulative = 0;
    $additional = 0;

    foreach ($intervals as $interval) {
        $duration = $interval['duration'];
        $out = $interval['out'];

        if ($out->format('H:i:s') <= '17:00:00') {
            $cumulative += $duration;
            continue;
        }

        if ($cumulative >= $thresholdSeconds) {
            $additional += $duration;
        } elseif ($cumulative + $duration > $thresholdSeconds) {
            $additional += ($cumulative + $duration) - $thresholdSeconds;
        }

        $cumulative += $duration;
    }

    return $additional;
}

// -------------------- FETCH PAYROLL NOTES --------------------

$periodStartKey = $rangeStart->format('Y-m-d');
$periodEndKey   = (clone $rangeStart)->modify('+6 day')->format('Y-m-d');

$notes = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            pn.note,
            pn.created_at,
            pn.updated_at,
            e.name AS created_by_name
        FROM payroll_notes pn
        LEFT JOIN employees e ON e.id = pn.created_by
        WHERE pn.period_start = ? AND pn.period_end = ?
        ORDER BY pn.created_at ASC, pn.id ASC
    ");

    $stmt->execute([$periodStartKey, $periodEndKey]);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $notes = [];

}

class PDF extends FPDF{

public $startDateFormatted;
public $endDateFormatted;
public $dayLabels=[];

function Header(){

$this->SetFont('Arial','B',14);
$this->Cell(0,8,'Employee Weekly Hours Report',0,1,'C');

$this->SetFont('Arial','',11);
$this->Cell(0,7,'From '.$this->startDateFormatted.' to '.$this->endDateFormatted,0,1,'C');

$this->Ln(4);

$this->SetFont('Arial','B',9);

$wEmp=45;
$wDay=22;

$this->Cell($wEmp,9,'Employee',1,0,'L');

foreach($this->dayLabels as $lbl){
$this->Cell($wDay,9,$lbl,1,0,'C');
}

$this->Cell(22,9,'Regular',1,0,'C');
$this->Cell(22,9,'Additional',1,0,'C');
$this->Cell(22,9,'Total',1,0,'C');

$this->Ln();

}

function Footer(){

$this->SetY(-12);
$this->SetFont('Arial','I',8);
$this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');

}

}

$pdf=new PDF('L','mm','A4');

$pdf->startDateFormatted=$rangeStart->format('F j, Y');
$pdf->endDateFormatted=$rangeEnd->format('F j, Y');

$labels=[];
foreach($days as $d){
$labels[]=$d->format('D n/j');
}

$pdf->dayLabels=$labels;

$pdf->AddPage();
$pdf->SetFont('Arial','',9);

$wEmp=45;
$wDay=22;

$totalRegular=0;
$totalHours=0;

$row=0;

foreach($employees as $emp){

$empId=(int)$emp['id'];
$name=$emp['name'];

$weekSeconds=$secondsByEmpWeek[$empId]??0;
$weekHours=$weekSeconds/3600;

$regularHours=min($weekHours,42.5);
$additionalHours=$weekHours>42.5
    ? calculateAdditionalSeconds($intervalsByEmp[$empId] ?? [], 42.5) / 3600
    : 0;

$totalRegular+=$regularHours;
$totalHours+=$weekHours;

$isOver50=$weekHours>50;

if($isOver50){
$pdf->SetFillColor(255,220,220);
}elseif($row%2){
$pdf->SetFillColor(245,245,245);
}else{
$pdf->SetFillColor(255,255,255);
}

$pdf->Cell($wEmp,8,pdfText($name),1,0,'L',true);

foreach($days as $d){

$key=$d->format('Y-m-d');

$sec=$secondsByEmpByDay[$empId][$key]??0;
$hrs=$sec/3600;

$presentFlag=$attendance[$empId][$key]??null;

if($presentFlag===0){

$pdf->Cell($wDay,8,'X',1,0,'C',true);

}else{

$pdf->Cell(
$wDay,
8,
$sec>0?number_format($hrs,2):'',
1,
0,
'R',
true
);

}

}

$pdf->Cell(22,8,number_format($regularHours,2),1,0,'R',true);

$pdf->Cell(22,8,number_format($additionalHours,2),1,0,'R',true);

$pdf->Cell(22,8,number_format($weekHours,2),1,0,'R',true);

$pdf->Ln();

$row++;

}

$pdf->Ln(6);

//$pdf->SetFont('Arial','B',11);
//$pdf->Cell(0,7,'Payroll Summary',0,1);

//$pdf->SetFont('Arial','',10);

//$pdf->Cell(60,6,'Total Regular Hours:',0,0);
//$pdf->Cell(20,6,number_format($totalRegular,2),0,1);

//$pdf->Cell(60,6,'Total Overtime Hours:',0,0);
//$pdf->Cell(20,6,number_format($totalOT,2),0,1);

//$pdf->Cell(60,6,'Total Hours:',0,0);
//$pdf->Cell(20,6,number_format($totalHours,2),0,1);

//$pdf->Cell(60,6,'Employees With OT:',0,0);
//$pdf->Cell(20,6,$employeesWithOT,0,1);

//$pdf->Ln(8);

$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,7,'Payroll Notes',0,1,'L');

$pdf->SetFont('Arial','I',9);
$pdf->Cell(0,6,pdfText("Period: {$periodStartKey} to {$periodEndKey}"),0,1,'L');

$pdf->SetFont('Arial','',10);

if (empty($notes)) {

    $pdf->SetFont('Arial','I',10);
    $pdf->MultiCell(0,6,pdfText('No notes were saved for this payroll period.'),1);

} else {

    foreach ($notes as $i => $n) {

        $by = $n['created_by_name'] ?? 'Unknown';
        $createdAt = $n['created_at'] ?? '';
        $text = trim((string)($n['note'] ?? ''));

        // Note header
        $pdf->SetFont('Arial','B',9);
        $pdf->MultiCell(
            0,
            5,
            pdfText(($i+1) . ") {$by} on {$createdAt}"),
            1
        );

        // Note body
        $pdf->SetFont('Arial','',10);
        $pdf->MultiCell(
            0,
            6,
            pdfText($text !== '' ? $text : '(blank note)'),
            1
        );

        $pdf->Ln(2);
    }

}

$filename='employee_weekly_hours_'.$rangeStart->format('Y-m-d').
'_to_'.$rangeEnd->format('Y-m-d').'.pdf';

$pdf->Output('D',$filename);

exit;