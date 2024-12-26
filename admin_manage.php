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
                $stmt = $conn->prepare("INSERT INTO syscus (CUSID, NICKNAME, PASSWORD, ROLE) VALUES (:cusid, :nickname, :password, :role)");
                $stmt->execute([
                    ':cusid' => $_POST['cusid'],
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
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .admin-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-container">
        <h2 class="mb-4">
            <i class="fas fa-users-cog"></i> 管理员管理
        </h2>


        <?php if($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <div class="row mb-3">
            <div class="col">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="fas fa-plus"></i> 添加管理员
                </button>
            </div>
        </div>


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
                        <button class="btn btn-sm btn-warning edit-admin" 
                            data-cusid="<?php echo $admin['CUSID']; ?>"
                            data-nickname="<?php echo $admin['NICKNAME']; ?>"
                            data-role="<?php echo $admin['ROLE']; ?>">
                            <i class="fas fa-edit"></i> 编辑
                        </button>
                        <a href="?action=delete&cusid=<?php echo $admin['CUSID']; ?>" 
                           class="btn btn-sm btn-danger delete-admin" 
                           onclick="return confirm('确定要删除此管理员吗?')">
                            <i class="fas fa-trash"></i> 删除
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
                        <label class="form-label">管理员ID</label>
                        <input type="text" name="cusid" class="form-control" required>
                    </div>
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
</div>


<script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
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