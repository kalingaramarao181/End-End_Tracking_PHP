<?php

require_once __DIR__ . '/model.php';

class PrimeVendorController
{
    private $primeVendorModel;

    public function __construct()
    {
        $this->primeVendorModel = new PrimeVendorModel();
    }

    public function create()
    {
        $data = $this->getRequestData();

        if (empty(trim($data['vcompany'] ?? ''))) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Vendor company is required.'
            ]);
        }

        $this->validateEmail($data);

        $result = $this->primeVendorModel->createPrimeVendor($data);
        $this->jsonResponse($result['success'] ? 201 : 500, $result);
    }

    public function index()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $result = $this->primeVendorModel->getPrimeVendors($page, $limit, $search);
        $this->jsonResponse($result['success'] ? 200 : 500, $result);
    }

    public function show($id)
    {
        $result = $this->primeVendorModel->getPrimeVendorById($id);
        $statusCode = $result['success']
            ? 200
            : (($result['message'] ?? '') === 'Prime vendor not found.' ? 404 : 500);

        $this->jsonResponse($statusCode, $result);
    }

    public function update($id)
    {
        $existing = $this->primeVendorModel->getPrimeVendorById($id);

        if (!$existing['success']) {
            $statusCode = ($existing['message'] ?? '') === 'Prime vendor not found.' ? 404 : 500;
            $this->jsonResponse($statusCode, $existing);
        }

        $data = $this->getRequestData();
        $fields = [
            'vcompany',
            'rname',
            'phone',
            'documents',
            'email',
            'fax',
            'caddress',
            'blocation',
            'feedback'
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = $existing['data'][$field];
            }
        }

        if (empty(trim($data['vcompany'] ?? ''))) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'Vendor company is required.'
            ]);
        }

        $this->validateEmail($data);

        $result = $this->primeVendorModel->updatePrimeVendor($id, $data);
        $this->jsonResponse($result['success'] ? 200 : 500, $result);
    }

    public function delete($id)
    {
        $result = $this->primeVendorModel->deletePrimeVendor($id);
        $statusCode = $result['success']
            ? 200
            : (($result['message'] ?? '') === 'Prime vendor not found.' ? 404 : 500);

        $this->jsonResponse($statusCode, $result);
    }

    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'multipart/form-data') !== false) {
            return $_POST;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        return is_array($data) ? $data : [];
    }

    private function validateEmail($data)
    {
        $email = trim($data['email'] ?? '');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(400, [
                'success' => false,
                'message' => 'A valid email address is required.'
            ]);
        }
    }

    private function jsonResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
