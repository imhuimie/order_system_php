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


// 获取菜品类型
$stmt = $conn->query("SELECT * FROM goodstype");
$food_types = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 处理添加菜品类型
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food_type'])) {
    $gtname = $_POST['gtname'];
    $gtdesc = $_POST['gtdesc'] ?? '';


    if (!empty($gtname)) {
        try {
            $stmt = $conn->prepare("INSERT INTO goodstype (GTNAME, GTDESC, GTSTATE) VALUES (:gtname, :gtdesc, 1)");
            $stmt->bindParam(':gtname', $gtname);
            $stmt->bindParam(':gtdesc', $gtdesc);
            
            $result = $stmt->execute();
            
            if ($result) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?type_success=1");
                exit();
            }
        } catch(PDOException $e) {
            $type_error = "添加菜品类型失败: " . $e->getMessage();
        }
    } else {
        $type_error = "菜品类型名称不能为空";
    }
}


// 处理添加菜品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food'])) {
    // 先处理文件上传
    $gimage = 'food1.png'; // 默认图片
    
    if (!empty($_FILES['file']['name'])) {
        $file = $_FILES['file'];
        
        // 创建 multipart 表单数据
        $curl = curl_init();
        $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);


        curl_setopt_array($curl, [
            CURLOPT_URL => SERVER_ADDR . "/foodadmin/addGoodsImg",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'fileImg' => $cfile
            ]
        ]);


        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $uploadResult = json_decode($response, true);
        curl_close($curl);


        // 检查图片上传是否成功
        if ($httpCode == 200 && isset($uploadResult['imageUrl'])) {
            $gimage = basename(parse_url($uploadResult['imageUrl'], PHP_URL_PATH));
        } else {
            $error = "图片上传失败:" . ($uploadResult['message'] ?? '未知错误');
            error_log("图片上传失败: " . print_r($response, true));
        }
    }
    
    // 准备菜品数据
    $foodData = [
        'gtid' => $_POST['gtid'],
        'gname' => $_POST['gname'],
        'gprice' => $_POST['gprice'],
        'gcontent' => $_POST['gcontent'] ?? '',
        'ginfo' => $_POST['ginfo'] ?? '',
        'gimage' => $gimage  // 将图片名传递给后端
    ];
    $curl = curl_init();
    $queryString = 'foodinfo=' . urlencode(json_encode($foodData));
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SERVER_ADDR . "/foodadmin/addGoods?" . $queryString,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true
    ]);


    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $result = json_decode($response, true);
    curl_close($curl);
    
    // 处理返回结果
    if ($httpCode == 200 && isset($result['success']) && $result['success']) {
        header("Location: food_manage.php?success=1");
        exit();
    } else {
        $error = "添加菜品失败: " . ($result['message'] ?? '未知错误');
    }
}


    
    try {
        $stmt = $conn->prepare("
            INSERT INTO goods (GTID, GNAME, GPRICE, GTIME, GINFO, GIMAGE) 
            VALUES (:gtid, :gname, :gprice, :gtime, :ginfo, :gimage)
        ");
        
        $stmt->execute($foodData);
        
        header("Location: food_manage.php?add_success=1");
        exit();
    } catch(PDOException $e) {
        $error = "添加菜品失败: " . $e->getMessage();
    }



// 处理删除菜品
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $gid = $_GET['id'];
    
    try {
        // 先获取图片信息，删除旧图片
        $stmt = $conn->prepare("SELECT GIMAGE FROM goods WHERE GID = :gid");
        $stmt->bindParam(':gid', $gid);
        $stmt->execute();
        $food = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($food && $food['GIMAGE'] != 'default.png') {
            $oldImagePath = 'uploads/' . $food['GIMAGE'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
        
        // 删除菜品
        $stmt = $conn->prepare("DELETE FROM goods WHERE GID = :gid");
        $stmt->bindParam(':gid', $gid);
        $stmt->execute();
        
        header("Location: food_manage.php?delete_success=1");
        exit();
    } catch(PDOException $e) {
        $delete_error = "删除菜品失败: " . $e->getMessage();
    }
}


// 获取菜品列表
$stmt = $conn->query("
    SELECT g.*, gt.GTNAME 
    FROM goods g 
    LEFT JOIN goodstype gt ON g.GTID = gt.GTID
");
$foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>菜品管理</title>
    
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
                        <a class="nav-link" href="admin_manage.php">
                            <i class="fas fa-users-cog me-2"></i>管理员管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="food_manage.php">
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
                    <i class="fas fa-utensils me-2"></i>菜品管理
                </h1>


                <!-- 提示消息 -->
                <?php if(isset($_GET['add_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>菜品添加成功！
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>


                <?php if(isset($_GET['delete_success'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-trash me-2"></i>菜品删除成功！
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>


                <!-- 添加菜品类型区域 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header card-header-custom">
                                <i class="fas fa-plus-circle me-2"></i>添加菜品类型
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">类型名称</label>
                                        <input type="text" name="gtname" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">类型描述</label>
                                        <textarea name="gtdesc" class="form-control"></textarea>
                                    </div>
                                    <button type="submit" name="add_food_type" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>保存类型
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header card-header-custom">
                                <i class="fas fa-list me-2"></i>现有菜品类型
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <?php foreach($food_types as $type): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($type['GTNAME']); ?>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo $type['GTSTATE'] == 1 ? '启用中' : '已禁用'; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- 添加菜品区域 -->
                <div class="card card-custom mb-4">
                    <div class="card-header card-header-custom">
                        <i class="fas fa-plus-circle me-2"></i>添加新菜品
                    </div>
                    <div class="card-body">
                        <form id="foodForm" action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label>菜品类型</label>
        <select name="gtid" class="form-control" required>
            <option value="">请选择类型</option>
            <?php foreach($goodsTypes as $type): ?>
                <option value="<?php echo $type['GTID']; ?>">
                    <?php echo htmlspecialchars($type['GTNAME']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>菜品名称</label>
        <input type="text" name="gname" class="form-control" required>
    </div>
    <div class="form-group">
        <label>价格</label>
        <input type="number" name="gprice" step="0.01" class="form-control" required>
    </div>
    <div class="form-group">
        <label>菜品描述</label>
        <textarea name="gcontent" class="form-control"></textarea>
    </div>
    <div class="form-group">
        <label>菜品备注</label>
        <input type="text" name="ginfo" class="form-control">
    </div>
    <div class="form-group">
        <label>准备时间(分钟)</label>
        <input type="number" name="gtime" class="form-control" value="5" required>
    </div>
    <div class="form-group">
        <label>菜品图片</label>
        <input type="file" name="file" class="form-control-file" required>
    </div>
    <button type="submit" name="add_food" class="btn btn-primary">添加菜品</button>
</form>
                    </div>
                </div>


                <!-- 菜品列表 -->
                <div class="card card-custom">
                    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-hamburger me-2"></i>菜品列表
                        </span>
                        <input type="text" class="form-control form-control-sm w-25 search-input" 
                               data-table="foodsTable" 
                               placeholder="搜索菜品...">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="foodsTable">
                                <thead>
                                    <tr>
                                        <th>图片</th>
                                        <th>菜品ID</th>
                                        <th>名称</th>
                                        <th>类型</th>
                                        <th>价格</th>
                                        <th>准备时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($foods as $food): ?>
                                    <tr>
                                        <td>
    <?php if(!empty($food['GIMG'])): ?>
        <img src="<?php echo htmlspecialchars(SERVER_ADDR . '/images/' . $food['GIMG']); ?>" 
             width="50" height="50" class="img-thumbnail">
    <?php else: ?>
        无图片
    <?php endif; ?>
</td>
                                        <td><?php echo htmlspecialchars($food['GID']); ?></td>
                                        <td><?php echo htmlspecialchars($food['GNAME']); ?></td>
                                        <td><?php echo htmlspecialchars($food['GTNAME'] ?? '未分类'); ?></td>
                                        <td>¥ <?php echo number_format($food['GPRICE'], 2); ?></td>
                                        <td><?php echo $food['GTIME']; ?> 分钟</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="#" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                   data-bs-target="#foodDetailModal" 
                                                   data-gid="<?php echo $food['GID']; ?>"
                                                   data-gname="<?php echo htmlspecialchars($food['GNAME']); ?>"
                                                   data-gprice="<?php echo $food['GPRICE']; ?>"
                                                   data-gtime="<?php echo $food['GTIME']; ?>"
                                                   data-ginfo="<?php echo htmlspecialchars($food['GINFO'] ?? ''); ?>"
                                                   data-gimage="<?php echo htmlspecialchars($food['GIMAGE']); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="food_manage.php?delete=1&id=<?php echo $food['GID']; ?>" 
                                                   class="btn btn-sm btn-danger btn-confirm" 
                                                   data-confirm="确定删除这个菜品吗？">
                                                    <i class="fas fa-trash"></i>
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




<!-- 菜品详情模态框 -->
<div class="modal fade" id="foodDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">菜品详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 text-center">
                        <img id="modalFoodImage" src="" alt="菜品图片" class="img-fluid rounded mb-3" style="max-height: 300px;">
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>菜品名称：</strong>
                            <p id="modalFoodName"></p>
                        </div>
                        <div class="mb-3">
                            <strong>价格：</strong>
                            <p id="modalFoodPrice"></p>
                        </div>
                        <div class="mb-3">
                            <strong>准备时间：</strong>
                            <p id="modalFoodTime"></p>
                        </div>
                        <div class="mb-3">
                            <strong>菜品描述：</strong>
                            <p id="modalFoodInfo"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>




<!-- 自定义JS -->
<script src="app.js"></script>




<script>
    // 菜品详情模态框处理
    document.addEventListener('DOMContentLoaded', function() {
        var foodDetailModal = document.getElementById('foodDetailModal');
        foodDetailModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var gname = button.getAttribute('data-gname');
            var gprice = button.getAttribute('data-gprice');
            var gtime = button.getAttribute('data-gtime');
            var ginfo = button.getAttribute('data-ginfo');
            var gimage = button.getAttribute('data-gimage');




            document.getElementById('modalFoodName').textContent = gname;
            document.getElementById('modalFoodPrice').textContent = '¥ ' + gprice;
            document.getElementById('modalFoodTime').textContent = gtime + ' 分钟';
            document.getElementById('modalFoodInfo').textContent = ginfo || '无描述';
            
            var imageElement = document.getElementById('modalFoodImage');
            imageElement.src = 'uploads/' + (gimage || 'default.png');
        });
    });
</script>
</body>
</html>