<?php
require_once __DIR__ . '/../../config/db.php';
function homeCount($sql){global $conn;$result=$conn->query($sql);return $result?(int)$result->fetch_assoc()['value']:0;}
function fetchPublicHomeSummary(){
    $queries=[
        'applications'=>"SELECT COUNT(*) value FROM application",
        'submissions'=>"SELECT COUNT(*) value FROM application WHERE process_id=1",
        'interviews'=>"SELECT COUNT(*) value FROM application_process_history WHERE event_type='interview'",
        'placements'=>"SELECT COUNT(*) value FROM application WHERE process_id=3",
        'candidates'=>"SELECT COUNT(*) value FROM candidate",
        'active_candidates'=>"SELECT COUNT(*) value FROM candidate WHERE status='Active'",
        'jobs'=>"SELECT COUNT(*) value FROM jobs",
        'active_jobs'=>"SELECT COUNT(*) value FROM jobs WHERE status='Open'",
        'vendors'=>"SELECT COUNT(*) value FROM primevendors",
        'clients'=>"SELECT COUNT(DISTINCT client) value FROM application WHERE client IS NOT NULL AND TRIM(client)<>''",
        'employees'=>"SELECT COUNT(*) value FROM employees",
        'active_employees'=>"SELECT COUNT(*) value FROM employees WHERE user_id IS NOT NULL",
        'users'=>"SELECT COUNT(*) value FROM users",
        'active_users'=>"SELECT COUNT(*) value FROM users WHERE status='Active'",
        'this_month'=>"SELECT COUNT(*) value FROM application WHERE date_created>=DATE_FORMAT(CURDATE(),'%Y-%m-01')",
        'last_month'=>"SELECT COUNT(*) value FROM application WHERE date_created>=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-01') AND date_created<DATE_FORMAT(CURDATE(),'%Y-%m-01')"
    ];
    $counts=[];foreach($queries as $key=>$sql)$counts[$key]=homeCount($sql);
    $counts['bench_resources']=$counts['active_candidates'];
    $previous=max(1,$counts['last_month']);$growth=round((($counts['this_month']-$counts['last_month'])/$previous)*100,1);
    global $conn;$trend=[];
    $result=$conn->query("SELECT DATE_FORMAT(day,'%d %b') label,value FROM (SELECT DATE(date_created) day,COUNT(*) value FROM application GROUP BY DATE(date_created) ORDER BY day DESC LIMIT 7) recent ORDER BY day");
    if($result)while($row=$result->fetch_assoc())$trend[]=['label'=>$row['label'],'value'=>(int)$row['value']];
    $yearly=[];$yearMap=[];
    $result=$conn->query("SELECT YEAR(activity_date) year,MONTH(activity_date) month,
        SUM(activity_type='submission') submissions,
        SUM(activity_type='interview') interviews,
        SUM(activity_type='placement') placements
        FROM (
            SELECT date_created activity_date,'submission' activity_type FROM application WHERE process_id=1 AND date_created IS NOT NULL
            UNION ALL
            SELECT created_at activity_date,'interview' activity_type FROM application_process_history WHERE event_type='interview' AND created_at IS NOT NULL
            UNION ALL
            SELECT COALESCE(placement_updated_at,date_created) activity_date,'placement' activity_type FROM application WHERE process_id=3
        ) activity
        GROUP BY YEAR(activity_date),MONTH(activity_date) ORDER BY year DESC,month");
    if($result)while($row=$result->fetch_assoc()){
        $year=(int)$row['year'];$month=(int)$row['month'];if($year<2000)continue;
        if(!isset($yearMap[$year]))$yearMap[$year]=['year'=>$year,'submissions'=>0,'interviews'=>0,'placements'=>0,'months'=>array_fill(0,12,['submissions'=>0,'interviews'=>0,'placements'=>0])];
        $monthData=['submissions'=>(int)$row['submissions'],'interviews'=>(int)$row['interviews'],'placements'=>(int)$row['placements']];
        $yearMap[$year]['months'][$month-1]=$monthData;
        foreach(['submissions','interviews','placements'] as $key)$yearMap[$year][$key]+=$monthData[$key];
    }
    $yearly=array_values($yearMap);
    $sources=[['name'=>'Submitted','value'=>$counts['submissions']],['name'=>'Interviews','value'=>$counts['interviews']],['name'=>'Placements','value'=>$counts['placements']],['name'=>'Clients','value'=>$counts['clients']]];
    return ['success'=>true,'counts'=>$counts,'growth'=>['applications'=>$growth],'trend'=>$trend,'yearly'=>$yearly,'sources'=>$sources,'updated_at'=>gmdate('c')];
}