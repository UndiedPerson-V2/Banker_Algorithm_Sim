<?php
// ตรวจสอบว่ามีการส่งค่าจากฟอร์มหรือไม่
if (isset($_POST['proceed'])) {
    $num_processes = (int)$_POST['num_processes'];
    $initial_available_str = trim($_POST['initial_available']);
    
    // แยก string ด้วยช่องว่าง และกรองค่าว่างออกเพื่อนับจำนวนทรัพยากร
    $available_values = array_filter(array_map('trim', explode(' ', $initial_available_str)));
    $num_resources = count($available_values);

    if ($num_resources === 0) {
        $error_message = "Error: Please enter at least one value for Available Resources.";
    } else {
        // เตรียมค่าสำหรับส่งไปยังหน้าถัดไป
        $query_data = http_build_query([
            'num_processes' => $num_processes,
            'initial_available' => $initial_available_str,
            'num_resources' => $num_resources,
            'auto_mode' => 1
        ]);

        if ($num_resources === 1) {
            // โหมด Single Resource (1 ค่า)
            header("Location: generate_table_single.php?" . $query_data);
            exit;
        } else {
            // โหมด Multi-Resource (มากกว่า 1 ค่า)
            header("Location: generate_table_multi.php?" . $query_data);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Banker's Algorithm Simulator</title>
    <style>
        body { font-family: sans-serif; background-color: #e6f7ff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        h1 { color: #333; }
        .input-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="number"], input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #4c8bf5; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background-color: #3b74d3; }
        .error { color: red; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Banker's Algorithm Simulator</h1>
        <p>Step 1: Define System Size</p>

        <?php if (isset($error_message)) { echo "<div class='error'>{$error_message}</div>"; } ?>

        <form method="POST">
            <div class="input-group">
                <label for="num_processes">Number of Processes (n)</label>
                <input type="number" id="num_processes" name="num_processes" value="3" min="1" required>
            </div>
            <div class="input-group">
                <label for="initial_available">Initial Available Resource (Vector or Single Value)</label>
                <input type="text" id="initial_available" name="initial_available" placeholder="e.g., 10 (Single) or 3 3 2 (Vector)" required>
                <small>Enter space-separated integer values. The system will automatically select the mode.</small>
            </div>
            <button type="submit" name="proceed">Proceed</button>
        </form>
    </div>
</body>
</html>