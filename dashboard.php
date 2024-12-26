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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理仪表盘</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 自定义样式 -->
    <link href="styles.css" rel="stylesheet">
    
    <!-- 图标库 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- ECharts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.3.0/dist/echarts.min.js"></script>
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
                        <a class="nav-link active" href="dashboard.php">
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
                    <i class="fas fa-tachometer-alt me-2"></i>管理仪表盘
                </h1>


                <!-- 统计卡片 -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card card-custom text-white bg-primary">
                            <div class="card-body d-flex align-items-center">
                                <i class="fas fa-shopping-cart fa-3x me-3"></i>
                                <div>
                                    <h5 class="card-title">总订单数</h5>
                                    <p class="card-text display-6"><?php echo $stats['total_orders']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="card card-custom text-white bg-success">
                            <div class="card-body d-flex align-items-center">
                                <i class="fas fa-dollar-sign fa-3x me-3"></i>
                                <div>
                                    <h5 class="card-title">总收入</h5>
                                    <p class="card-text display-6">¥ <?php echo number_format($stats['total_revenue'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="card card-custom text-white bg-info">
                            <div class="card-body d-flex align-items-center">
                                <i class="fas fa-hamburger fa-3x me-3"></i>
                                <div>
                                    <h5 class="card-title">菜品总数</h5>
                                    <p class="card-text display-6"><?php echo $stats['total_foods']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="card card-custom text-white bg-warning">
                            <div class="card-body d-flex align-items-center">
                                <i class="fas fa-users fa-3x me-3"></i>
                                <div>
                                    <h5 class="card-title">客户总数</h5>
                                    <p class="card-text display-6"><?php echo $stats['total_customers']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                                <!-- 详细信息区 -->
                <div class="row g-4">
                    <!-- 最近订单 -->
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header card-header-custom">
                                <i class="fas fa-receipt me-2"></i>最近订单
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="recentOrdersTable">
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
                        </div>
                    </div>


                    <!-- 热销菜品 -->
                    <div class="col-md-6">
                        <div class="card card-custom">
                            <div class="card-header card-header-custom">
                                <i class="fas fa-fire me-2"></i>热销菜品
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="hotFoodsTable">
                                        <thead>
                                            <tr>
                                                <th>菜品名称</th>
                                                <th>销售数量</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($hot_foods as $food): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($food['GNAME']); ?></td>
                                                <td><?php echo $food['total_count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- 数据可视化 -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card card-custom">
                            <div class="card-header card-header-custom">
                                <i class="fas fa-chart-line me-2"></i>销售趋势
                            </div>
                            <div class="card-body">
                                <div id="salesChart" style="height: 400px;"></div>
                            </div>
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
    // ECharts 销售趋势图
    document.addEventListener('DOMContentLoaded', function() {
        var salesChart = echarts.init(document.getElementById('salesChart'));
        
        var option = {
            tooltip: {
                trigger: 'axis'
            },
            xAxis: {
                type: 'category',
                data: ['1月', '2月', '3月', '4月', '5月', '6月']
            },
            yAxis: {
                type: 'value'
            },
            series: [{
                name: '销售额',
                type: 'line',
                data: [820, 932, 901, 934, 1290, 1330],
                smooth: true
            }]
        };


        salesChart.setOption(option);
    });
</script>
</body>
</html>