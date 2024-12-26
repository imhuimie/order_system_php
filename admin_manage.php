<?php
session_start();
require_once 'db_config.php';


// 权限验证
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$database = new Database();
$conn = $database->getConnection();


// 处理管理员操作
$action = $_GET['action'] ?? '';
$message = '';


try {
    switch($action) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // 生成唯一的 CUSID
                $cusid = uniqid();
                
                $stmt = $conn->prepare("INSERT INTO syscus (CUSID, NICKNAME, PASSWORD, ROLE, LIMITS) VALUES (:cusid, :nickname, :password, :role, 'true')");
                $stmt->execute([
                    ':cusid' => $cusid,
                    ':nickname' => $_POST['nickname'],
                    ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    ':role' => $_POST['role']
                ]);
                $message = "管理员添加成功";
            }
            break;


        case 'edit':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $stmt = $conn->prepare("UPDATE syscus SET NICKNAME = :nickname, ROLE = :role WHERE CUSID = :cusid");
                $stmt->execute([
                    ':cusid' => $_POST['cusid'],
                    ':nickname' => $_POST['nickname'],
                    ':role' => $_POST['role']
                ]);
                $message = "管理员信息更新成功";
            }
            break;


        case 'delete':
            $stmt = $conn->prepare("DELETE FROM syscus WHERE CUSID = :cusid");
            $stmt->execute([':cusid' => $_GET['cusid']]);
            $message = "管理员删除成功";
            break;
    }
} catch(PDOException $e) {
    $message = "操作失败: " . $e->getMessage();
}


// 获取管理员列表
$stmt = $conn->prepare("SELECT * FROM syscus");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员管理 - 餐厅管理系统</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 自定义样式 -->
    <link href="styles.css" rel="stylesheet">
    
    <!-- 图标库 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- 侧边导航 -->
        <div class="col-md-2 sidebar">
            <div class="py-4">
                <div class="text-center mb-4">
                    <img src="logo.png" alt="Logo" class="img-fluid rounded-circle" style="max-width: 100px;">
                    <h5 class="mt-3 text-white"><?php echo $_SESSION['nickname']; ?></h5>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>仪表盘
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_manage.php">
                            <i class="fas fa-users-cog me-2"></i>管理员管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="food_manage.php">
                            <i class="fas fa-utensils me-2"></i>菜品管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="order_manage.php">
                            <i class="fas fa-list-alt me-2"></i>订单管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="login.php">
                            <i class="fas fa-sign-out-alt me-2"></i>退出登录
                        </a>
                    </li>
                </ul>
            </div>
        </div>


        <!-- 主内容区 -->
        <div class="col-md-10 bg-light">
            <div class="container-custom">
                <h1 class="my-4">
                    <i class="fas fa-users-cog me-2"></i>管理员管理
                </h1>


                <?php if($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>


                <div class="card card-custom mb-4">
                    <div class="card-header card-header-custom">
                        <i class="fas fa-users me-2"></i>管理员列表
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                    <i class="fas fa-plus"></i> 添加管理员
                                </button>
                            </div>
                        </div>


                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>管理员ID</th>
                                        <th>昵称</th>
                                        <th>角色</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['CUSID']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['NICKNAME']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['ROLE']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-warning edit-admin" 
                                                    data-cusid="<?php echo $admin['CUSID']; ?>"
                                                    data-nickname="<?php echo $admin['NICKNAME']; ?>"
                                                    data-role="<?php echo $admin['ROLE']; ?>">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </button>
                                                <a href="?action=delete&cusid=<?php echo $admin['CUSID']; ?>" 
                                                   class="btn btn-sm btn-danger delete-admin" 
                                                   onclick="return confirm('确定要删除                                                   此管理员吗?')">
                                                    <i class="fas fa-trash"></i> 删除
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- 添加管理员模态框 -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">添加管理员</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">昵称</label>
                        <input type="text" name="nickname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">角色</label>
                        <select name="role" class="form-select" required>
                            <option value="admin">管理员</option>
                            <option value="operator">操作员</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- 编辑管理员模态框 -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑管理员</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=edit">
                <div class="modal-body">
                    <input type="hidden" name="cusid" id="edit-cusid">
                    <div class="mb-3">
                        <label class="form-label">昵称</label>
                        <input type="text" name="nickname" id="edit-nickname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">角色</label>
                        <select name="role" id="edit-role" class="form-select" required>
                            <option value="admin">管理员</option>
                            <option value="operator">操作员</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


<!-- 自定义JS -->
<script src="app.js"></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // 编辑管理员按钮事件
    const editButtons = document.querySelectorAll('.edit-admin');
    const editModal = new bootstrap.Modal(document.getElementById('editAdminModal'));
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit-cusid').value = this.dataset.cusid;
            document.getElementById('edit-nickname').value = this.dataset.nickname;
            document.getElementById('edit-role').value = this.dataset.role;
            editModal.show();
        });
    });


    // 删除管理员确认
    const deleteButtons = document.querySelectorAll('.delete-admin');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('确定要删除此管理员吗?')) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>