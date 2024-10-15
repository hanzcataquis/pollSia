<?php
session_start();
include '../includes/db.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Handle poll creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_poll'])) {
    $question = $_POST['question'];

    // Insert new poll into the database
    $stmt = $pdo->prepare("INSERT INTO polls (question) VALUES (?)");
    $stmt->execute([$question]);

    // Get the ID of the newly created poll
    $poll_id = $pdo->lastInsertId();

    // Insert options for the poll
    if (isset($_POST['options']) && !empty($_POST['options'])) {
        foreach ($_POST['options'] as $option) {
            if (!empty($option)) {
                $stmt = $pdo->prepare("INSERT INTO options (poll_id, option_text) VALUES (?, ?)");
                $stmt->execute([$poll_id, $option]);
            }
        }
    }
}

// Handle poll deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $poll_id = $_GET['id'];

    // Delete poll from the database
    $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
    $stmt->execute([$poll_id]);
}

// Logout functionality
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

// Fetch existing polls with options
$stmt = $pdo->query("SELECT * FROM polls");
$polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Admin Dashboard</title>
</head>
<body>
    <!-- Sticky Navbar -->
    <nav class="navbar navbar-expand-lg">
        <h2 class="navbar-brand">Admin Dashboard</h2>
        <div class="ml-auto">
            <a href="adRegister.php" class="btn btn-success mr-2">Add New Admin</a>
            <a href="?action=logout" class="btn btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Create New Poll Form -->
        <div class="card mb-4">
            <div class="card-body">
                <h3>Create New Poll</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="text" name="question" class="form-control" placeholder="Poll Question" required>
                    </div>
                    <div id="options-container">
                        <div class="form-group">
                            <input type="text" name="options[]" class="form-control" placeholder="Option 1" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="options[]" class="form-control" placeholder="Option 2" required>
                        </div>
                    </div>
                    <button type="button" id="add-option-btn" class="btn btn-secondary mb-3">Add Option</button>
                    <button type="submit" name="create_poll" class="btn btn-primary">Create Poll</button>
                </form>
            </div>
        </div>

        <!-- Display Existing Polls in Cards -->
        <h3>Existing Polls</h3>
        <div class="row">
            <?php if (empty($polls)): ?>
                <p>No polls available.</p>
            <?php else: ?>
                <?php foreach ($polls as $poll): ?>
                    <div class="col-md-4">
                        <div class="card poll-card mb-3" data-toggle="modal" data-target="#pollModal<?= $poll['id']; ?>">
                            <div class="card-body">
                                <h4><?= htmlspecialchars($poll['question']); ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Modal for Poll CRUD -->
                    <div class="modal fade" id="pollModal<?= $poll['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="pollModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= htmlspecialchars($poll['question']); ?></h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <h5>Options:</h5>
                                    <?php
                                    // Fetch options for this poll
                                    $options_stmt = $pdo->prepare("SELECT * FROM options WHERE poll_id = ?");
                                    $options_stmt->execute([$poll['id']]);
                                    $options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Fetch votes for this poll
                                    $votes_stmt = $pdo->prepare("SELECT option_id, COUNT(*) as votes FROM votes WHERE poll_id = ? GROUP BY option_id");
                                    $votes_stmt->execute([$poll['id']]);
                                    $vote_results = $votes_stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Total votes count
                                    $total_votes = array_sum(array_column($vote_results, 'votes'));
                                    ?>
                                    <ul class="list-group mb-3">
                                        <?php foreach ($options as $option): ?>
                                            <?php
                                            $vote_count = 0;
                                            foreach ($vote_results as $result) {
                                                if ($result['option_id'] == $option['id']) {
                                                    $vote_count = $result['votes'];
                                                    break;
                                                }
                                            }
                                            // Calculate percentage
                                            $percentage = $total_votes ? ($vote_count / $total_votes) * 100 : 0;
                                            ?>
                                            <li class="list-group-item">
                                                <?= htmlspecialchars($option['option_text']); ?>
                                                <div class="bar-container">
                                                    <div class="bar" style="width: <?= $percentage; ?>%;"></div>
                                                </div>
                                                <?= round($percentage, 2); ?>% (<?= $vote_count; ?> votes)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <a href="update_poll.php?id=<?= $poll['id']; ?>" class="btn btn-warning mt-3">Edit</a>
                                    <a href="?action=delete&id=<?= $poll['id']; ?>" class="btn btn-danger mt-3" onclick="return confirm('Are you sure you want to delete this poll?');">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- JS for Adding Dynamic Poll Options -->
    <script>
        document.getElementById('add-option-btn').addEventListener('click', function() {
            const optionsContainer = document.getElementById('options-container');
            const newOptionIndex = optionsContainer.children.length + 1;
            const newOption = `
                <div class="form-group">
                    <input type="text" name="options[]" class="form-control" placeholder="Option ${newOptionIndex}" required>
                </div>
            `;
            optionsContainer.insertAdjacentHTML('beforeend', newOption);
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
