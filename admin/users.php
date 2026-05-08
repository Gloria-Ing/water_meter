<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getDB();

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'client'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header('Location: users.php');
        exit();
    }
}

// Get all users
$users = $conn->query("
    SELECT u.*, 
           COALESCE(SUM(ud.liters), 0) as total_usage,
           COALESCE(SUM(ud.bill), 0) as total_bill
    FROM users u
    LEFT JOIN usage_data ud ON u.id = ud.user_id
    WHERE u.role = 'client'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
        }
        .nav-links {
            display: flex;
            gap: 20px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
        }
        .nav-links a:hover {
            background: #34495e;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 5px 10px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }
        .btn-small {
            background: #3498db;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .add-btn {
            background: #27ae60;
            padding: 10px 20px;
            margin-bottom: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Management</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="payments.php">Payments</a>
            <a href="reports.php">Reports</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="section">
            <h2>All Users</h2>
            <a href="add_user.php" class="btn add-btn">+ Add New User</a>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Tariff</th>
                        <th>Total Usage</th>
                        <th>Total Bill</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td><?php echo formatCurrency($user['tariff']); ?>/1000L</td>
                        <td><?php echo formatLiters($user['total_usage']); ?></td>
                        <td><?php echo formatCurrency($user['total_bill']); ?></td>
                        <td><?php echo formatCurrency($user['balance']); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-small">Edit</a>
                            <a href="?delete=<?php echo $user['id']; ?>" class="btn" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>