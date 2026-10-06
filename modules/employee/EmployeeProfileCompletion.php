<?php
// Mirrors the required-field calculation used on the employee profile page.
function employeeProfileCompletionPercentage(array $employee,array $collection,array $payroll):int {
    $checks=[];
    $add=function($value)use(&$checks){$checks[]=trim((string)($value??''))!==''&&$value!=='0000-00-00';};
    foreach(['firstname','lastname','contact_info'] as $key)$add($employee[$key]??'');
    $add(!empty($collection['email'])?$collection['email']:($employee['payroll_email']??''));
    foreach(['birthdate','gender'] as $key)$add($employee[$key]??'');
    foreach(['father_name','mother_name'] as $key)$add($collection[$key]??'');
    foreach(['bank_name','bank_account_number','ifsc_code','pan_number'] as $key)$add($payroll[$key]??'');
    $categories=array_column($collection['documents']??[],'category');
    $document=function($category)use($add,$categories){$add(in_array($category,$categories,true)?'uploaded':'');};
    $education=$collection['education']??[];
    if(!$education)$add('');
    foreach($education as $row){foreach(['degree','college','passing_year'] as $key)$add($row[$key]??'');$document('education_'.$row['id'].'_certificate');$document('education_'.$row['id'].'_marksheets');}
    if(!empty($collection['has_experience']))foreach($collection['employment']??[] as $row){foreach(['company','designation','period'] as $key)$add($row[$key]??'');$document('experience_'.$row['id']);}
    foreach($collection['certifications']??[] as $row){$add($row['name']??'');$document('certification_'.$row['id']);}
    return (int)round(count(array_filter($checks))/count($checks)*100);
}
