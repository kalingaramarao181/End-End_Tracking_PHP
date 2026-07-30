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

    public function executiveSummary()
    {
        $this->respond($this->model->getExecutiveSummary(authUser()));
    }

    public function activities()
    {
        $this->respond($this->model->getAuditActivities(authUser(), $_GET['limit'] ?? 30));
    }

    public function workforceAnalytics()
    {
        $employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] !== ''
            ? (int)$_GET['employee_id']
            : null;
        $this->respond($this->model->getWorkforceAnalytics(
            authUser(),
            $employeeId,
            $_GET['period'] ?? 'this_week'
        ));
    }

    public function profilePerformance()
    {
        $candidateId = !empty($_GET['candidate_id']) ? (int)$_GET['candidate_id'] : null;
        $userId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        if (!$candidateId && !$userId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'candidate_id or user_id is required.']);
            exit;
        }
        $this->respond($this->model->getProfilePerformance(
            authUser(),
            $candidateId,
            $userId
        ));
    }

    private function respond(array $result)
    {
        http_response_code($result['success'] ? 200 : 500);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
