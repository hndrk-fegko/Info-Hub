<?php

class ApiResponder {

    public function json(array $payload, int $status = 200): void {
        http_response_code($status);
        echo json_encode($payload);
    }

    public function success(array $payload = [], int $status = 200): void {
        $this->json(['success' => true] + $payload, $status);
    }

    public function result(array $result, int $failureStatus = 400): void {
        $this->json($result, !empty($result['success']) ? 200 : $failureStatus);
    }
}