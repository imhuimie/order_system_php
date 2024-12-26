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


// 统计数据
$stats = [
    'total_orders' => 0,
    'total_revenue' => 0,
    'total_foods' => 0,
    'total_customers' => 0
];


// 总订单数
$stmt = $conn->query("SELECT COUNT(*) as order_count FROM overorder");
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['order_count'];


// 总收入
$stmt = $conn->query("SELECT SUM(ORDERTOTLEPRICE) as total_revenue FROM overorder");
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];


// 菜品总数
$stmt = $conn->query("SELECT COUNT(*) as food_count FROM goods");
$stats['total_foods'] = $stmt->fetch(PDO::FETCH_ASSOC)['food_count'];


// 客户总数
$stmt = $conn->query("SELECT COUNT(*) as customer_count FROM syscus");
$stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['customer_count'];


// 最近订单
$stmt = $conn->query("SELECT * FROM overorder ORDER BY ORDERTIME DESC LIMIT 5");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 热销菜品
$stmt = $conn->query("
    SELECT g.GNAME, SUM(od.GCOUNT) as total_count 
    FROM orderdetail od 
    JOIN goods g ON od.GID = g.GID 
    GROUP BY g.GNAME 
    ORDER BY total_count DESC 
    LIMIT 5
");
$hot_foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理仪表盘</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/echarts/5.1.2/echarts.min.js"></script>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">总订单数</div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $stats['total_orders']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">总收入</div>
                <div class="card-body">
                    <h3 class="card-title">¥ <?php echo number_format($stats['total_revenue'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">菜品总数</div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $stats['total_foods']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-header">客户总数</div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $stats['total_customers']; ?></h3>
                </div>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">最近订单</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>订单ID</th>
                            <th>客户ID</th>
                            <th>总价</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo substr($order['ORDERID'], 0, 8); ?>...</td>
                            <td><?php echo substr($order['CUSID'], 0, 8); ?>...</td>
                            <td>¥ <?php echo $order['ORDERTOTLEPRICE']; ?></td>
                            <td><?php echo date('Y-m-d H:i', $order['ORDERTIME']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">热销菜品</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>菜品名称</th>
                            <th>销售数量</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($hot_foods as $food): ?>
                        <tr>
                            <td><?php echo $food['GNAME']; ?></td>
                            <td><?php echo $food['total_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">操作中心</div>
                <div class="card-body">
                    <a href="admin_manage.php" class="btn btn-primary mr-2">管理员管理</a>
                    <a href="food_manage.php" class="btn btn-success mr-2">菜品管理</a>
                    <a href="order_manage.php" class="btn btn-info mr-2">订单管理</a>
                    <a href="login.php" class="btn btn-danger">退出登录</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>