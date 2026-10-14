<?php
function resumeMatchValidateHistory($input) {
    if (!is_array($input)) throw new InvalidArgumentException('Invalid history request.');
    if (!is_string($input['job_description'] ?? null)) throw new InvalidArgumentException('Enter a valid job description.');
    $jd = trim($input['job_description']);
    $source = $input['source'] ?? '';
    if (!is_string($input['state_filter'] ?? 'ALL')) throw new InvalidArgumentException('Choose a valid US state.');
    $state = strtoupper(trim($input['state_filter'] ?? 'ALL'));
    $states = explode(' ', 'ALL AL AK AZ AR CA CO CT DE FL GA HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT NE NV NH NJ NM NY NC ND OH OK OR PA RI SC SD TN TX UT VT VA WA WV WI WY');
    $count = filter_var($input['candidate_count'] ?? null, FILTER_VALIDATE_INT);
    $score = filter_var($input['top_score'] ?? null, FILTER_VALIDATE_INT);
    $jdLength = function_exists('mb_strlen') ? mb_strlen($jd,'UTF-8') : preg_match_all('/./us',$jd);
    if ($jdLength < 50 || $jdLength > 5000) throw new InvalidArgumentException('History requires a job description between 50 and 5,000 characters.');
    if (!in_array($source, ['upload', 'demo'], true)) throw new InvalidArgumentException('Choose uploads or labelled demo search.');
    if (!in_array($state, $states, true)) throw new InvalidArgumentException('Choose a valid US state.');
    if ($count === false || $count < 1 || $count > 50) throw new InvalidArgumentException('Compare between 1 and 50 candidates.');
    if ($score === false || $score < 0 || $score > 100) throw new InvalidArgumentException('Invalid match score.');
    return ['job_description'=>$jd,'state_filter'=>$state,'source'=>$source,'candidate_count'=>$count,'top_score'=>$score];
}
function resumeMatchHistoryReady($conn) {
    $result=$conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='resume_match_history'");
    return $result && $result->num_rows > 0;
}
function resumeMatchHistoryList($conn,$userId) {
    $stmt=$conn->prepare('SELECT id, job_description, state_filter, source, candidate_count, top_score, created_at FROM resume_match_history WHERE user_id=? ORDER BY id DESC LIMIT 30');
    $stmt->bind_param('i',$userId);$stmt->execute();
    $result=$stmt->get_result();$rows=[];
    while($row=$result->fetch_assoc()){$row['id']=(int)$row['id'];$row['candidate_count']=(int)$row['candidate_count'];$row['top_score']=(int)$row['top_score'];$rows[]=$row;}
    return $rows;
}
function resumeMatchHistorySave($conn,$userId,$record) {
    $stmt=$conn->prepare('INSERT INTO resume_match_history (user_id,job_description,state_filter,source,candidate_count,top_score,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())');
    $stmt->bind_param('isssii',$userId,$record['job_description'],$record['state_filter'],$record['source'],$record['candidate_count'],$record['top_score']);
    $stmt->execute();return ['id'=>(int)$conn->insert_id];
}
