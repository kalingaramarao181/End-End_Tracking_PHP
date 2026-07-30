<?php
require_once __DIR__ . '/../../config/db.php';
function fetchPublicHomeSummary(){
    global $conn;
    $queries=[
        'candidates'=>"SELECT COUNT(*) value FROM candidate",
        'active_jobs'=>"SELECT COUNT(*) value FROM jobs WHERE status='Open'",
        'submissions'=>"SELECT COUNT(*) value FROM application",
        'placements'=>"SELECT COUNT(*) value FROM application WHERE process_id=3",
        'bench_resources'=>"SELECT COUNT(*) value FROM candidate WHERE status='Active'",
        'clients'=>"SELECT COUNT(DISTINCT client) value FROM application WHERE client IS NOT NULL AND TRIM(client)<>''",
        'vendors'=>"SELECT COUNT(*) value FROM primevendors",
        'employees'=>"SELECT COUNT(*) value FROM employees WHERE user_id IS NOT NULL"
    ];
    $counts=[];foreach($queries as $key=>$sql){$result=$conn->query($sql);$counts[$key]=$result?(int)$result->fetch_assoc()['value']:0;}
    $trend=[];$result=$conn->query("SELECT DATE_FORMAT(date_created,'%a') label,DATE(date_created) day,COUNT(*) value FROM application WHERE date_created>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(date_created),DATE_FORMAT(date_created,'%a') ORDER BY day");
    if($result)while($row=$result->fetch_assoc())$trend[]=['label'=>$row['label'],'value'=>(int)$row['value']];
    $sources=[
        ['name'=>'Direct','value'=>max(0,$counts['submissions']-$counts['vendors']-$counts['placements'])],
        ['name'=>'Vendors','value'=>$counts['vendors']],
        ['name'=>'Placements','value'=>$counts['placements']],
        ['name'=>'Clients','value'=>$counts['clients']]
    ];
    return ['success'=>true,'counts'=>$counts,'trend'=>$trend,'sources'=>$sources,'updated_at'=>gmdate('c')];
}