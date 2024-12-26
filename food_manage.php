<?php
session_start();
require_once 'db_config.php';


// 定义服务器地址常量
define('SERVER_ADDR', 'https://wx.544444.xyz');


// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$database = new Database();
$conn = $database->getConnection();


// 获取菜品类型列表
try {
    $stmt = $conn->prepare("SELECT * FROM goodstype WHERE GTSTATE = 1");
    $stmt->execute();
    $goodsTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "获取菜品类型失败: " . $e->getMessage();
}


// 获取菜品列表
try {
    $stmt = $conn->prepare("SELECT g.*, gt.GTNAME FROM goods g 
                            JOIN goodstype gt ON g.GTID = gt.GTID 
                            WHERE g.GSTATE = 1");
    $stmt->execute();
    $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "获取菜品列表失败: " . $e->getMessage();
}


// 处理删除菜品
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $gid = $_GET['id'];
    
    try {
        // 先查询图片信息
        $stmt = $conn->prepare("SELECT GIMG FROM goods WHERE GID = :gid");
        $stmt->bindParam(':gid', $gid);
        $stmt->execute();
        $food = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 删除数据库记录
        $stmt = $conn->prepare("DELETE FROM goods WHERE GID = :gid");
        $stmt->bindParam(':gid', $gid);
        $result = $stmt->execute();
        
        if ($result) {
            // 如果有图片,尝试删除服务器上的图片文件
            if (!empty($food['GIMG'])) {
                // 提取文件名
                $filename = basename(parse_url($food['GIMG'], PHP_URL_PATH));
                
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => SERVER_ADDR . "/foodadmin/deleteGoodsImg",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => "DELETE",
                    CURLOPT_POSTFIELDS => json_encode([
                        'filename' => $filename
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json'
                    ]
                ]);
                
                $response = curl_exec($curl);
                curl_close($curl);
            }
            
            header("Location: food_manage.php?delete_success=1");
            exit();
        }
    } catch(PDOException $e) {
        error_log("删除菜品失败: " . $e->getMessage());
        header("Location: food_manage.php?delete_error=1");
        exit();
    }
}


// 处理添加菜品类型
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food_type'])) {
    $gtname = $_POST['gtname'] ?? '';
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
            $error = "图片上传失败：" . ($uploadResult['message'] ?? '未知错误');
            error_log("图片上传失败: " . print_r($response, true));
        }
    }


    // 准备发送菜品数据
    $foodData = [
        'gtid' => $_POST['gtid'],
        'gname' => $_POST['gname'],
        'gprice' => $_POST['gprice'],
        'gcontent' => $_POST['gcontent'] ?? '',
        'ginfo' => $_POST['ginfo'] ?? '',
        'gimage' => $gimage  // 将图片名传递给后端
    ];


    // 发送菜品数据到后端
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


    if ($httpCode == 200 && isset($result['result']['code']) && $result['result']['code'] == 200) {
        header("Location: food_manage.php?success=1");
        exit();
    } else {
        $error = "添加菜品失败：" . ($result['result']['msg'] ?? '未知错误');
        error_log("添加菜品失败: " . print_r($response, true));
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>菜品管理</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- 左侧菜单栏 -->
        <nav class="col-md-2 d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="food_manage.php">
                            <i class="fas fa-utensils"></i> 菜品管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="order_manage.php">
                            <i class="fas fa-list-alt"></i> 订单管理
                        </a>
                    </li>
                </ul>
            </div>
        </nav>


        <!-- 主内容区域 -->
        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">菜品管理</h1>
            </div>


            <!-- 添加菜品类型 -->
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">添加菜品类型</div>
                        <div class="card-body">
                            <?php 
                            if (isset($type_error)) {
                                echo '<div class="alert alert-danger">' . htmlspecialchars($type_error) . '</div>';
                            }
                            if (isset($_GET['type_success'])) {
                                echo '<div class="alert alert-success">菜品类型添加成功!</div>';
                            }
                            ?>
                            <form action="" method="post">
                                <div class="form-group">
                                    <label>类型名称</label>
                                    <input type="text" name="gtname" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>类型描述</label>
                                    <textarea name="gtdesc" class="form-control"></textarea>
                                </div>
                                <button type="submit" name="add_food_type" class="btn btn-primary btn-block">添加类型</button>
                            </form>
                        </div>
                    </div>
                </div>


                <!-- 添加菜品 -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">添加菜品</div>
                        <div class="card-body">
                            <?php 
                            if (isset($error)) {
                                echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
                            }
                            if (isset($_GET['success'])) {
                                echo '<div class="alert alert-success">菜品添加成功!</div>';
                            }
                            ?>
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
                                     <input type="file" name="file" id="fileUpload" class="form-control-file" accept="image/*">
                                </div>
                                <button type="submit" name="add_food" class="btn btn-success btn-block">添加菜品</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>


            <!-- 菜品列表 -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">菜品列表</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>菜品名称</th>
                                        <th>价格</th>
                                        <th>类型</th>
                                        <th>准备时间</th>
                                        <th>图片</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($foods as $food): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($food['GNAME']); ?></td>
                                        <td><?php echo number_format($food['GPRICE'], 2); ?> 元</td>
                                        <td><?php echo htmlspecialchars($food['GTNAME']); ?></td>
                                        <td><?php echo $food['GTIME']; ?> 分钟</td>
                                        <td>
                                            <?php if(!empty($food['GIMG'])): ?>
                                                <img src="<?php echo htmlspecialchars(SERVER_ADDR . '/images/' . $food['GIMG']); ?>" 
                                                     width="50" height="50" class="img-thumbnail">
                                            <?php else: ?>
                                                无图片
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_food.php?id=<?php echo $food['GID']; ?>" 
                                                                                                      class="btn btn-sm btn-warning">编辑</a>
                                                <a href="food_manage.php?delete=1&id=<?php echo $food['GID']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('确定删除这个菜品吗?')">删除</a>
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
        </main>
    </div>
</div>


<!-- 引入必要的 JS 和图标库 -->
<script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>


<style>
    .sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 100;
        padding: 48px 0 0;
        box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    }
</style>
</body>
</html>