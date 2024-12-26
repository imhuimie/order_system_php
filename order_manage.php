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




// 更新订单状态
if (isset($_GET['complete_orderid'])) {
    $orderid = $_GET['complete_orderid'];
    
    try {
        // 查询原订单信息
        $stmt = $conn->prepare("SELECT * FROM cusorders WHERE ORDERID = :orderid");
        $stmt->bindParam(':orderid', $orderid);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // 将订单插入完成订单表
            $stmt = $conn->prepare("INSERT INTO overorder (ORDERID, CUSID, ORDERTIME, ORDERSTATE, ORDERTOTLEPRICE) VALUES (:orderid, :cusid, :ordertime, 3, :totleprice)");
            $stmt->bindParam(':orderid', $order['ORDERID']);
            $stmt->bindParam(':cusid', $order['CUSID']);
            $stmt->bindParam(':ordertime', $order['ORDERTIME']);
            $stmt->bindParam(':totleprice', $order['ORDERTOTLEPRICE']);
            $stmt->execute();
            
            // 删除原订单
            $stmt = $conn->prepare("DELETE FROM cusorders WHERE ORDERID = :orderid");
            $stmt->bindParam(':orderid', $orderid);
            $stmt->execute();
        }
    } catch(PDOException $e) {
        $error = "订单处理失败: " . $e->getMessage();
    }
}




// 取消订单
if (isset($_GET['cancel_orderid'])) {
    $orderid = $_GET['cancel_orderid'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM cusorders WHERE ORDERID = :orderid");
        $stmt->bindParam(':orderid', $orderid);
        $stmt->execute();
    } catch(PDOException $e) {
        $error = "取消订单失败: " . $e->getMessage();
    }
}




// 获取所有未完成订单和订单详情
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
            'ordertime' => $order['ORDERTIME'],
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
?>




<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>订单管理</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>未完成订单</h2>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if(empty($processedOrders)): ?>
        <div class="alert alert-info">暂无未完成订单</div>
    <?php else: ?>
        <?php foreach($processedOrders as $order): ?>
            <div class="card mb-3">
                <div class="card-header">
                    订单号: <?php echo htmlspecialchars($order['orderid']); ?>
                    <span class="float-right">
                        客户ID: <?php echo htmlspecialchars($order['cusid']); ?>
                    </span>
                </div>
                <div class="card-body">
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
                            <?php foreach($order['items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['gname']); ?></td>
                                    <td><?php echo htmlspecialchars($item['gcount']); ?></td>
                                    <td><?php echo htmlspecialchars($item['gprice']); ?></td>
                                    <td><?php echo $item['gcount'] * $item['gprice']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-right">
                        <strong>总计: <?php echo $order['totleprice']; ?> 元</strong>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="order_manage.php?complete_orderid=<?php echo $order['orderid']; ?>" 
                       class="btn btn-success" 
                       onclick="return confirm('确定完成此订单吗？')">完成订单</a>
                    <a href="order_manage.php?cancel_orderid=<?php echo $order['orderid']; ?>" 
                       class="btn btn-danger" 
                       onclick="return confirm('确定取消此订单吗？')">取消订单</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">返回仪表盘</a>
    </div>
</div>
</body>
</html>