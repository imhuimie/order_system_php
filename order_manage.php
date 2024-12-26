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


// 增加错误日志记录
error_reporting(E_ALL);
ini_set('display_errors', 1);


// 更新订单状态
if (isset($_GET['complete_orderid'])) {
    $orderid = $_GET['complete_orderid'];
    
    try {
        // 开启数据库事务
        $conn->beginTransaction();


        // 查询原订单信息
        $stmt = $conn->prepare("SELECT * FROM cusorders WHERE ORDERID = :orderid");
        $stmt->bindParam(':orderid', $orderid);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // 查询订单详情
            $detailStmt = $conn->prepare("SELECT * FROM orderdetail WHERE ORDERID = :orderid");
            $detailStmt->bindParam(':orderid', $orderid);
            $detailStmt->execute();
            $orderDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);


            // 获取当前时间戳
            $currentTimestamp = time();


            // 插入完成订单主表
            $insertOverOrderStmt = $conn->prepare("
                INSERT INTO overorder 
                (ORDERID, CUSID, ORDERTIME, ORDERSTATE, ORDERTOTLEPRICE, COMPLETEDAT) 
                VALUES (:orderid, :cusid, :ordertime, 3, :totleprice, NOW())
            ");
            $insertOverOrderStmt->execute([
                ':orderid' => $order['ORDERID'],
                ':cusid' => $order['CUSID'],
                ':ordertime' => $currentTimestamp, // 使用当前时间戳
                ':totleprice' => $order['ORDERTOTLEPRICE']
            ]);


            // 插入完成订单详情
            $insertOverDetailStmt = $conn->prepare("
                INSERT INTO overorderdetail 
                (ORDERID, GID, GNAME, GCOUNT, GPRICE, GTIME) 
                VALUES (:orderid, :gid, :gname, :gcount, :gprice, :gtime)
            ");


            foreach ($orderDetails as $detail) {
                $insertOverDetailStmt->execute([
                    ':orderid' => $orderid,
                    ':gid' => $detail['GID'],
                    ':gname' => $detail['GNAME'],
                    ':gcount' => $detail['GCOUNT'],
                    ':gprice' => $detail['GPRICE'],
                    ':gtime' => $detail['GTIME'] ?? 0 // 提供默认值
                ]);
            }


            // 删除原订单
            $conn->prepare("DELETE FROM orderdetail WHERE ORDERID = :orderid")
                 ->execute([':orderid' => $orderid]);
            $conn->prepare("DELETE FROM cusorders WHERE ORDERID = :orderid")
                 ->execute([':orderid' => $orderid]);


            // 提交事务
            $conn->commit();
            
            header("Location: order_manage.php?success=1");
            exit();
        } else {
            throw new Exception("未找到订单");
        }
    } catch(Exception $e) {
        // 回滚事务
        $conn->rollBack();
        
        // 记录详细错误日志
        error_log("订单处理失败: " . $e->getMessage());
        header("Location: order_manage.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}


// 取消订单
if (isset($_GET['cancel_orderid'])) {
    $orderid = $_GET['cancel_orderid'];
    
    try {
        // 开启事务
        $conn->beginTransaction();


        // 删除订单详情
        $stmt = $conn->prepare("DELETE FROM orderdetail WHERE ORDERID = :orderid");
        $stmt->bindParam(':orderid', $orderid);
        $stmt->execute();


        // 删除主订单
        $stmt = $conn->prepare("DELETE FROM cusorders WHERE ORDERID = :orderid");
        $stmt->bindParam(':orderid', $orderid);
        $stmt->execute();


        // 提交事务
        $conn->commit();
        
        header("Location: order_manage.php?cancel_success=1");
        exit();
    } catch(PDOException $e) {
        // 回滚事务
        $conn->rollBack();
        
        // 记录详细错误日志
        error_log("取消订单失败: " . $e->getMessage());
        header("Location: order_manage.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}


// 获取所有未完成订单和订单详情
try {
    $stmt = $conn->query("
        SELECT co.*, od.GNAME, od.GCOUNT, od.GPRICE 
        FROM cusorders co 
        JOIN orderdetail od ON co.ORDERID = od.ORDERID
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // 组织订单数据
    $processedOrders = [];
    foreach ($orders as $order) {
        $orderid = $order['ORDERID'];
        if (!isset($processedOrders[$orderid])) {
            $processedOrders[$orderid] = [
                'orderid' => $orderid,
                'cusid' => $order['CUSID'],
                'ordertime' => strtotime($order['ORDERTIME']), // 转换为时间戳
                'totleprice' => $order['ORDERTOTLEPRICE'],
                'items' => []
            ];
        }
        
        $processedOrders[$orderid]['items'][] = [
            'gname' => $order['GNAME'],
            'gcount' => $order['GCOUNT'],
            'gprice' => $order['GPRICE']
        ];
    }
} catch(PDOException $e) {
    error_log("获取订单失败: " . $e->getMessage());
    $processedOrders = [];
}
?>




<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单管理</title>
    
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
                        <a class="nav-link" href="food_manage.php">
                            <i class="fas fa-utensils me-2"></i>菜品管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="order_manage.php">
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
                    <i class="fas fa-list-alt me-2"></i>订单管理
                </h1>




                <!-- 提示信息 -->
                <?php if(isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>订单完成成功！
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>




                <?php if(isset($_GET['cancel_success'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>订单已取消！
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>




                <!-- 搜索和筛选 -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control search-input" 
                               data-table="ordersTable" 
                               placeholder="搜索订单...">
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary filter-btn" data-filter="all">
                                <i class="fas fa-list me-2"></i>全部订单
                            </button>
                            <button class="btn btn-outline-success filter-btn" data-filter="completed">
                                <i class="fas fa-check-circle me-2"></i>已完成
                            </button>
                            <button class="btn btn-outline-warning filter-btn" data-filter="pending">
                                <i class="fas fa-clock me-2"></i>待处理
                            </button>
                        </div>
                    </div>
                </div>


                <!-- 订单列表 -->
                <div class="card card-custom">
                    <div class="card-header card-header-custom">
                        <i class="fas fa-shopping-cart me-2"></i>未完成订单列表
                    </div>
                    <div class="card-body">
                        <?php if(empty($processedOrders)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>暂无未完成订单
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="ordersTable">
                                    <thead>
                                        <tr>
                                            <th>订单ID</th>
                                            <th>客户ID</th>
                                            <th>下单时间</th>
                                            <th>总金额</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($processedOrders as $order): ?>
                                        <tr data-order-id="<?php echo $order['orderid']; ?>">
                                            <td><?php echo substr($order['orderid'], 0, 8); ?>...</td>
                                            <td><?php echo substr($order['cusid'], 0, 8); ?>...</td>
                                            <td><?php echo date('Y-m-d H:i', $order['ordertime']); ?></td>
                                            <td>¥ <?php echo number_format($order['totleprice'], 2); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#orderDetailModal"
                                                            data-order-details='<?php echo json_encode($order); ?>'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="order_manage.php?complete_orderid=<?php echo $order['orderid']; ?>" 
                                                       class="btn btn-sm btn-success btn-confirm" 
                                                       data-confirm="确定完成此订单吗？">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="order_manage.php?cancel_orderid=<?php echo $order['orderid']; ?>" 
                                                       class="btn btn-sm btn-danger btn-confirm" 
                                                       data-confirm="确定取消此订单吗？">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- 订单详情模态框 -->
<div class="modal fade" id="orderDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">订单详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailContent">
                <!-- 动态填充订单详情 -->
            </div>
        </div>
    </div>
</div>


<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


<!-- 自定义JS -->
<script src="app.js"></script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 订单详情模态框处理
        var orderDetailModal = document.getElementById('orderDetailModal');
        orderDetailModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var orderDetails = JSON.parse(button.getAttribute('data-order-details'));
            var modalContent = document.getElementById('orderDetailContent');


            var detailHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>订单ID:</strong>
                        <p>${orderDetails.orderid}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>客户ID:</strong>
                        <p>${orderDetails.cusid}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>下单时间:</strong>
                        <p>${new Date(orderDetails.ordertime * 1000).toLocaleString()}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>总金额:</strong>
                        <p>¥ ${orderDetails.totleprice}</p>
                    </div>
                </div>
                <hr>
                <h6>订单明细</h6>
                <table class="table">
                    <thead>
                        <tr>
                            <th>菜品名称</th>
                            <th>数量</th>
                            <th>单价</th>
                            <th>小计</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${orderDetails.items.map(item => `
                            <tr>
                                <td>${item.gname}</td>
                                <td>${item.gcount}</td>
                                <td>¥ ${item.gprice}</td>
                                <td>¥ ${(item.gcount * item.gprice).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;


            modalContent.innerHTML = detailHtml;
        });


        // 筛选按钮处理
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                const table = document.getElementById('ordersTable');
                const rows = table.querySelectorAll('tbody tr');


                rows.forEach(row => {
                    row.style.display = 'table-row';
                });
            });
        });
    });
</script>
</body>
</html>