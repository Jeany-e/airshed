<?php
include "config.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to submit feedback.']);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true);
$rating = filter_var($payload['rating'] ?? null, FILTER_VALIDATE_INT);

if ($rating === false || $rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a rating from 1 to 5 stars.']);
    exit();
}

try {
    firebasePush('feedback', [
        'user_id' => (string) $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'Anonymous',
        'rating' => $rating,
        'timestamp' => gmdate('c')
    ]);
    echo json_encode(['success' => true]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'Feedback could not be saved right now.']);
}