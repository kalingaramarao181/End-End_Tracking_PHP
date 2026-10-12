<?php
require_once __DIR__.'/service.php';
if (!publicEmployeeAccess()) exit;
$path=rtrim($request,'/');
$isList=$path==='/public/employees';
$isPhoto=preg_match('#^/public/employees/([1-9][0-9]{0,9})/photo$#D',$path,$matches);
if (!$isList && !$isPhoto) {publicEmployeeError(404,'Public employee route not found.');exit;}
try {
    if ($isList) $filters=publicEmployeeFilters($_GET);
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    require_once __DIR__.'/../../config/db.php';
    if (!$conn->set_charset('utf8mb4')) throw new RuntimeException('Unable to configure database encoding.');
    if ($isPhoto) {
        require_once __DIR__.'/../employee/EmployeeCollectionService.php';
        require_once __DIR__.'/../employee/payroll.php';
        require_once __DIR__.'/../employee/EmployeePhotoService.php';
        employeeReadPhoto((int)$matches[1]);
    } else {
        $directory=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']));
        $basePath=in_array($directory,['/','.'],true)?'':rtrim($directory,'/');
        echo json_encode(publicEmployeeList($conn,$filters,$basePath),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);
    }
} catch (InvalidArgumentException $error) {
    publicEmployeeError(422,$error->getMessage());
} catch (Throwable $error) {
    error_log('Public employee API: '.$error->getMessage());
    publicEmployeeError(503,'Employee directory is temporarily unavailable.');
}
exit;