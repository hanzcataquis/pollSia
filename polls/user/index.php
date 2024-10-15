<?php
session_start();
include '../includes/db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: register.php');
    exit;
}

// Check for logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch all polls
$stmt = $pdo->query("SELECT * FROM polls");
$polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check user votes
$user_id = $_SESSION['user_id'];
$user_votes_stmt = $pdo->prepare("SELECT poll_id, option_id FROM votes WHERE user_id = ?");
$user_votes_stmt->execute([$user_id]);
$user_votes = $user_votes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create an associative array of user's votes for easy lookup
$user_votes_assoc = [];
foreach ($user_votes as $vote) {
    $user_votes_assoc[$vote['poll_id']] = $vote['option_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <title>User Dashboard</title>
</head>
<body>

    <!-- Sticky Navbar with Available Polls and Logout Button -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Advanced Polls</a>
            <div class="d-flex">
                <a href="?action=logout" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <?php foreach ($polls as $poll): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 poll-card">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($poll['question']); ?></h5>
                            <form method="POST" action="vote.php">
                                <?php
                                // Fetch poll options
                                $options_stmt = $pdo->prepare("SELECT * FROM options WHERE poll_id = ?");
                                $options_stmt->execute([$poll['id']]);
                                $options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);

                                // Check if user has already voted for this poll
                                $user_voted_option = isset($user_votes_assoc[$poll['id']]) ? $user_votes_assoc[$poll['id']] : null;
                                ?>

                                <?php foreach ($options as $option): ?>
                                    <div class="form-check <?= $user_voted_option == $option['id'] ? 'voted-option' : ''; ?>">
                                        <input class="form-check-input" type="radio" name="option_id" value="<?= $option['id']; ?>" 
                                        <?php if ($user_voted_option || in_array($poll['id'], array_keys($user_votes_assoc))): ?> 
                                            disabled <?php endif; ?> 
                                        required>
                                        <label class="form-check-label">
                                            <?= htmlspecialchars($option['option_text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>

                                <input type="hidden" name="poll_id" value="<?= $poll['id']; ?>">
                                <?php if (in_array($poll['id'], array_keys($user_votes_assoc))): ?>
                                    <p class="voted-message">You have already voted for this poll.</p>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary mt-2 vote-button">Vote</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
