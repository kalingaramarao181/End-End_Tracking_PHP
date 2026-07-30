<?php
require_once __DIR__.'/model.php';
function publicHomeSummary(){echo json_encode(fetchPublicHomeSummary());}