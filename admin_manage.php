<?php
session_start();
require_once 'db_config.php';


// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$database = new Database();
$conn = $database->getConnection();


// 添加管理员
if (isset($_POST['add_admin'])) {
    $new_cusid = uniqid();
    $new_nickname = $_POST['new_nickname'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO syscus (CUSID, NICKNAME, LIMITS) VALUES (:cusid, :nickname, 'false')");
        $stmt->bindParam(':cusid', $new_cusid);
        $stmt->bindParam(':nickname', $new_nickname);
        $stmt->execute();
    } catch(PDOException $e) {
        $error = "添加管理员失败: " . $e->getMessage();
    }
}


// 删除管理员
if (isset($_GET['delete_cusid'])) {
    $delete_cusid = $_GET['delete_cusid'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM syscus WHERE CUSID = :cusid");
        $stmt->bindParam(':cusid', $delete_cusid);
        $stmt->execute();
    } catch(PDOException $e) {
        $error = "删除管理员失败: " . $e->getMessage();
    }
}


// 获取所有管理员
$stmt = $conn->query("SELECT * FROM syscus");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理员管理</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h2>管理员列表</h2>
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>管理员ID</th>
                        <th>昵称</th>
                        <th>权限</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($admins as $admin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($admin['CUSID']); ?></td>
                        <td><?php echo htmlspecialchars($admin['NICKNAME']); ?></td>
                        <td><?php echo $admin['LIMITS'] == 'true' ? '超级管理员' : '普通管理员'; ?></td>
                        <td>
                            <a href="admin_manage.php?delete_cusid=<?php echo $admin['CUSID']; ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('确定要删除此管理员吗？')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">添加管理员</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>管理员昵称</label>
                            <input type="text" name="new_nickname" class="form-control" required>
                        </div>
                        <button type="submit" name="add_admin" class="btn btn-primary btn-block">添加</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">返回仪表盘</a>
    </div>
</div>
</body>
</html>