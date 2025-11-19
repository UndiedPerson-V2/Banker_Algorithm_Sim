<?php
// ตรวจสอบว่ามีการส่งค่าจากฟอร์มมาหรือไม่
if (!isset($_POST['data'], $_POST['num_processes'], $_POST['initial_work'])) {
    header("Location: index.php");
    exit;
}

$num_processes = (int)$_POST['num_processes'];
$current_work = (int)$_POST['initial_work']; // กำหนดค่า Work เริ่มต้น
$data = $_POST['data']; 
$is_safe = true;
$safe_sequence = [];

// เตรียมข้อมูลให้ง่ายต่อการใช้งาน
$allocation = $data['allocation'];
$need = $data['need'];
$max = $data['max'];
$finish = array_fill_keys(array_keys($allocation), false); // ตั้งค่า Finish เริ่มต้นเป็น False ทั้งหมด


// ฟังก์ชันสำหรับการรัน Banker's Safety Algorithm 

// ใช้ Anonymous Function (Closure) ในการหาวงจร Safe Sequence
$bankers_safety_algorithm = function() use (&$current_work, &$finish, &$allocation, &$need, &$safe_sequence, $num_processes) {
    
    $count = 0; // ตัวนับ Process ที่เสร็จแล้ว
    $check_count = 0; // ตัวนับการวนลูป (ป้องกัน Infinite Loop)

    // วนลูปจนกว่าจะครบทุก Process หรือจนกว่าจะวนครบหนึ่งรอบแล้วไม่มี Process ใดเสร็จเลย
    while ($count < $num_processes && $check_count < $num_processes * $num_processes) {
        $found_process = false;
        
        // วนลูปหา Process ที่สามารถทำงานต่อได้
        foreach ($finish as $process => $status) {
            // Step 2: เลือก P ที่ Finish False และ Need <= Work
            // Note: สำหรับ Single Resource เราสามารถเปรียบเทียบเป็นตัวเลขธรรมดาได้เลย
            if ($status === false && $need[$process] <= $current_work) {
                
                // Step 3: จำลองการทำงานเมื่อเสร็จสมบูรณ์ work = (work + allocation)
                $current_work += $allocation[$process];
                $finish[$process] = true; // ตั้งค่า Finish เป็น True
                $safe_sequence[] = $process; // บันทึก Safe Sequence
                $count++;
                $found_process = true;
                break; // เริ่มวนลูปใหม่ (Step 4 เช็ค Need อีกครั้ง)
            }
        }
        
        // ถ้าวนครบทุก Process แล้วไม่พบ P ที่ทำงานต่อได้ แสดงว่าติด Deadlock (Unsafe)
        if (!$found_process && $count < $num_processes) {
            return false; // ไม่ปลอดภัย
        }
        
        $check_count++;
    }
    
    return $count === $num_processes; // ปลอดภัยถ้าทุก Process เสร็จ
};

// รัน Algorithm
$is_safe = $bankers_safety_algorithm();

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Banker's Algorithm Result</title>
    <style>
        body { font-family: sans-serif; background-color: #e6f7ff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 600px; }
        .result-box { margin-top: 20px; padding: 20px; border-radius: 5px; }
        .safe { background-color: #e8f5e9; border: 2px solid #4CAF50; }
        .unsafe { background-color: #ffebee; border: 2px solid #F44336; }
        h3 { margin-top: 0; }
        table { border-collapse: collapse; margin: 20px auto; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #e0f2ff; font-weight: bold; }
        .finish-true { color: green; font-weight: bold; }
        .finish-false { color: red; font-weight: bold; }
        .button-group { margin-top: 30px; }
        .button-group a { background-color: #4c8bf5; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="margin-bottom: 0;">Banker's Algorithm Result</h2>
        <h3 class="result-box <?php echo $is_safe ? 'safe' : 'unsafe'; ?>">
            <?php 
            if ($is_safe) {
                echo "ยินดีด้วย Process นี้ปลอดภัย (Secure)";
            } else {
                echo "Process นี้ไม่ปลอดภัย (Not Secure)";
            }
            ?>
        </h3>

        <table>
            <thead>
                <tr>
                    <th>Process</th>
                    <th>Allocation</th>
                    <th>Need</th>
                    <th>Max</th>
                    <th>Finish</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($allocation as $process => $alloc_val) {
                    echo "<tr>";
                    echo "<td>{$process}</td>";
                    echo "<td>{$alloc_val}</td>";
                    echo "<td>{$need[$process]}</td>";
                    echo "<td>{$max[$process]}</td>";
                    
                    // แสดงสถานะ Finish ที่ได้จากการคำนวณ
                    $final_status = $finish[$process] ? 'True' : 'False';
                    $class = $finish[$process] ? 'finish-true' : 'finish-false';
                    echo "<td class='{$class}'>{$final_status}</td>";
                    echo "</tr>";
                }
                ?>
                <tr>
                    <td colspan="5" style="text-align: left; font-weight: bold;">
                        Work Value เริ่มต้น: <?php echo $_POST['initial_work']; ?> 
                        <?php if ($is_safe) { ?>
                            | Safe Sequence: <?php echo implode(' → ', $safe_sequence); ?>
                        <?php } ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="button-group">
            <a href="index.php">กลับไปหน้าเริ่มต้น</a>
        </div>
    </div>
</body>
</html>