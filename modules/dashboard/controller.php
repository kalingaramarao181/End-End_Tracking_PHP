<?php

require_once __DIR__ . '/model.php';

class DashboardController
{
    private $model;

    public function __construct()
    {
        $this->model = new DashboardModel();
    }

    public function table($table)
    {
        $result = $this->model->getTableData($table, $_GET, authUser());
        http_response_code($result['success'] ? 200 : 500);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    public function show($table, $id)
    {
        $result = $this->model->getRecord($table, $id, authUser());
        $status = $result['success'] ? 200 : (!empty($result['not_found']) ? 404 : 500);
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
