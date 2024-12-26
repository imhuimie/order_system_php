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
            $error = "登录失败,请检查您的凭证";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    
    <!-- 引入 Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 自定义样式 -->
    <link href="styles.css" rel="stylesheet">
    
    <!-- 图标库 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom shadow-lg border-0">
                    <div class="card-header card-header-custom text-center">
                        <h3 class="mb-0">
                            <i class="fas fa-lock me-2"></i>管理员登录
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if($error): ?>
                            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>


                        <form method="POST" id="loginForm" novalidate>
                            <div class="mb-3">
                                <label for="cusid" class="form-label">
                                    <i class="fas fa-user me-2"></i>管理员ID
                                </label>
                                <input 
                                    type="text" 
                                    name="cusid" 
                                    id="cusid" 
                                    class="form-control" 
                                    required 
                                    placeholder="请输入管理员ID"
                                >
                                <div class="invalid-feedback">请输入管理员ID</div>
                            </div>


                            <div class="mb-3">
                                <label for="nickname" class="form-label">
                                    <i class="fas fa-signature me-2"></i>管理员昵称
                                </label>
                                <input 
                                    type="text" 
                                    name="nickname" 
                                    id="nickname" 
                                    class="form-control" 
                                    required 
                                    placeholder="请输入管理员昵称"
                                >
                                <div class="invalid-feedback">请输入管理员昵称</div>
                            </div>


                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>登录
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center bg-transparent border-0 pb-4">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-2"></i>请使用正确的管理员凭证
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 自定义JS -->
    <script src="app.js"></script>
</body>
</html>