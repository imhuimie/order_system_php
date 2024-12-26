<?php
session_start();
require_once 'db_config.php';


$error = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cusid = $_POST['cusid'];
    $nickname = $_POST['nickname'];


    $database = new Database();
    $conn = $database->getConnection();


    try {
        $stmt = $conn->prepare("SELECT * FROM syscus WHERE CUSID = :cusid AND NICKNAME = :nickname");
        $stmt->bindParam(':cusid', $cusid);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($user) {
            $_SESSION['user_id'] = $user['CUSID'];
            $_SESSION['nickname'] = $user['NICKNAME'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "登录失败，请检查您的凭证";
        }
    } catch(PDOException $e) {
        $error = "数据库错误: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理员登录</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">管理员登录</div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>管理员ID</label>
                            <input type="text" name="cusid" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>管理员昵称</label>
                            <input type="text" name="nickname" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">登录</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>