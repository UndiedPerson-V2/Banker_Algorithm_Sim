<?php
// Check if values are submitted via GET (Auto Redirect)
if (!isset($_GET['auto_mode'], $_GET['num_processes'], $_GET['initial_available'])) {
    if (!isset($_POST['calculate'])) {
        header("Location: index.php");
        exit;
    }
}

// Determine source of data (GET or POST)
$is_initial_load = !isset($_POST['calculate']);
$source = $is_initial_load ? $_GET : $_POST;

$num_processes = (int)$source['num_processes'];
$initial_available_str = trim($source['initial_available']);
$initial_work = (int)$initial_available_str; 

$show_input_form = $is_initial_load;
$is_safe = false;
$is_invalid = false; 
$safe_sequence = [];
$current_work = $initial_work; 
$execution_log = [];

$allocation = [];
$need = []; 
$max = [];
$finish = [];

if (isset($_POST['calculate'])) {
    // --- Calculation (Single Resource) ---
    $data = $_POST['data']; 
    $allocation = $data['allocation'];
    $max = $data['max']; 
    $current_work = $initial_work; 

    // 1. Calculate Need and Validate Input
    $need = [];
    $is_invalid = false;
    foreach ($allocation as $process => $alloc_val) {
        $need_val = $max[$process] - $alloc_val;
        
        // Validation: Allocation cannot be greater than Max (Need < 0)
        if ($need_val < 0) {
            $execution_log[] = "❌❌ **Input Validation Error:** Process {$process} violates the Banker's rule (Allocation ({$alloc_val}) > Max ({$max[$process]})). Need is negative.";
            $is_invalid = true;
            break; 
        }
        $need[$process] = $need_val;
    }
    
    if (!$is_invalid) {
        $finish = array_fill_keys(array_keys($allocation), false); 

        // Banker's Safety Algorithm (Single Resource) 
        $bankers_safety_algorithm = function() use (&$current_work, &$finish, &$allocation, &$need, &$safe_sequence, $num_processes, &$execution_log) {
            $count = 0; 
            $initial_work_snapshot = $current_work;
            $execution_log[] = "System Status: Start Safety Check.";
            $execution_log[] = "Initial Work: {$initial_work_snapshot}";
            
            $check_count = 0; 

            while ($count < $num_processes && $check_count < $num_processes * $num_processes) {
                $found_process = false;
                $execution_log[] = "--- Cycle " . ($count + 1) . " (Current Work: {$current_work}) ---";

                foreach ($finish as $process => $status) {
                    if ($status === false) {
                        
                        // Step 2: Need[i] <= Work
                        $need_val = $need[$process];
                        
                        if ($need_val <= $current_work) {
                            
                            // Step 3: Simulate Execution (Non-Blocked Step)
                            $work_before = $current_work;
                            $current_work += $allocation[$process];
                            
                            $finish[$process] = true;
                            $safe_sequence[] = $process;
                            $count++;
                            $found_process = true;
                            
                            // Log successful step
                            $execution_log[] = "✅ Process **{$process}** can finish because Need ({$need_val}) **&le;** Work ({$work_before}).";
                            $execution_log[] = "   New Work = Work + Allocation: {$work_before} + {$allocation[$process]} = **{$current_work}**";
                            
                            break; 
                        } else {
                            // Log blocked step
                            $execution_log[] = "❌ Process {$process} cannot finish: Need ({$need_val}) **&notle;** Work ({$current_work}).";
                        }
                    }
                }
                
                if (!$found_process && $count < $num_processes) {
                    // Capture details of blocked processes
                    $blocked_processes = [];
                    foreach ($finish as $process => $status) {
                        if ($status === false) {
                            $blocked_processes[] = "{$process} (Need: {$need[$process]})";
                        }
                    }
                    
                    $execution_log[] = "💔 **DEADLOCK DETECTED (UNSAFE STATE)** at Cycle " . ($count + 1) . ".";
                    $execution_log[] = "   Current Work (Available): {$current_work}";
                    $execution_log[] = "   Blocked Processes: " . implode(', ', $blocked_processes) . " (Cannot satisfy Need **&le;** Work)";
                    return false; 
                }
                
                $check_count++;
            }
            
            $execution_log[] = "System Status: All {$num_processes} processes finished successfully.";
            return true;
        };

        $is_safe = $bankers_safety_algorithm();
    } // End if (!is_invalid)
    
    $show_input_form = false; 
} else {
    // Initialize empty arrays for first load (for display only)
    for ($p = 1; $p <= $num_processes; $p++) {
        $allocation["P{$p}"] = 0; 
        $max["P{$p}"] = 0;
    }
}

// ** Lambda Function for Manual Input Field (Allocation and Max) **
$create_input_field = function($p_index, $type) use ($allocation, $max) {
    $name = "data[{$type}][P{$p_index}]";
    $value_array = ($type === 'allocation') ? $allocation : $max;
    // ใช้ ?? 0 เพื่อป้องกัน Warning เมื่อคีย์ไม่มีอยู่
    $value = $value_array["P{$p_index}"] ?? '0'; 
    
    return "<td><input type='number' name='{$name}' min='0' value='{$value}' required style='width: 50px;'></td>";
};

// ** Lambda Function for Disabled Need Field **
$create_disabled_need_field = function($p_index) use ($allocation, $max) {
    $p_key = "P" . $p_index;
    
    // ใช้ ?? 0 เพื่อป้องกัน Warning เมื่อคีย์ไม่มีอยู่
    $current_alloc = $allocation[$p_key] ?? 0;
    $current_max = $max[$p_key] ?? 0;
    
    $calculated_need = max(0, $current_max - $current_alloc); 
    
    return "<td><input type='text' value='{$calculated_need}' disabled style='width: 50px; background-color: #eee; color: #666;'></td>";
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Banker's Algorithm Single Resource</title>
    <style>
        body { font-family: sans-serif; background-color: #e6f7ff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 600px; }
        .header { background-color: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        h2 { color: #333; margin: 0; }
        table { border-collapse: collapse; margin: 20px auto; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #e0f2ff; font-weight: bold; }
        input[type="number"] { width: 50px; padding: 5px; border: 1px solid #ddd; border-radius: 3px; }
        input[type="text"][disabled] { width: 50px; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        
        .result-box { margin-top: 20px; padding: 20px; border-radius: 5px; }
        .safe { background-color: #e8f5e9; border: 2px solid #4CAF50; }
        .unsafe { background-color: #ffebee; border: 2px solid #F44336; }
        .finish-true { color: green; font-weight: bold; }
        .finish-false { color: red; font-weight: bold; }
        .button-group { margin-top: 30px; display: flex; justify-content: space-between; }
        .button-group button, .button-group a { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .calculate { background-color: #4c8bf5; color: white; }
        .calculate:hover { background-color: #3b74d3; }
        .back { background-color: #f44336; color: white; }
        .back:hover { background-color: #d32f2f; }
        .note { margin-top: 20px; color: #666; font-size: 14px; }
        .log-list { list-style-type: none; padding-left: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Banker's Algorithm Single Resource Mode</h2>
            <p><?php echo $show_input_form ? 'Step 2: Enter Allocation / Max' : 'Calculation Result'; ?></p>
        </div>
        
        <?php if ($show_input_form) { ?>
        <form method="POST">
            <input type="hidden" name="num_processes" value="<?php echo $num_processes; ?>">
            <input type="hidden" name="initial_available" value="<?php echo $initial_work; ?>">
            
            <table>
                <thead>
                    <tr>
                        <th>Process</th>
                        <th>Allocation</th>
                        <th>Need (Calculated)</th> 
                        <th>Max</th>
                        <th>Finish</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    for ($p = 1; $p <= $num_processes; $p++) { 
                        echo "<tr>";
                        echo "<td>P{$p}</td>";
                        echo $create_input_field($p, 'allocation'); 
                        echo $create_disabled_need_field($p); 
                        echo $create_input_field($p, 'max'); 
                        echo "<td><input type='text' value='False' disabled style='width: 50px; background-color: #f7f7f7;'></td>";
                        echo "</tr>";
                    } 
                    ?>
                    <tr>
                        <td colspan="5">
                            **Available/Work Value (Initial):** **<?php echo $initial_work; ?>**
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="button-group">
                <button type="submit" name="calculate" class="calculate">Calculate System State</button>
                <a href="index.php" class="back">Back to Initial Setup</a>
            </div>
            <p class="note">Enter Allocation and Max values manually. The Need value (Max - Allocation) is displayed but cannot be edited. Ensure Allocation $\le$ Max.</p>
        </form>

        <?php } else { ?>
        <h3 class="result-box <?php echo $is_safe && !$is_invalid ? 'safe' : 'unsafe'; ?>">
            <?php 
            if ($is_invalid) {
                echo "⛔ **INPUT ERROR!** Please review the Execution Log for details.";
            } elseif ($is_safe) {
                echo "✅ The system is in a **SAFE** state!";
            } else {
                echo "❌ **DEADLOCK DETECTED!** The system is in an **UNSAFE** state.";
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
                    
                    $final_status = $finish[$process] ? 'True' : 'False';
                    $class = $finish[$process] ? 'finish-true' : 'finish-false';
                    echo "<td class='{$class}'>{$final_status}</td>";
                    echo "</tr>";
                }
                ?>
                <tr>
                    <td colspan="5" style="text-align: left; font-weight: bold;">
                        Initial Work: <?php echo $initial_work; ?> 
                        | Final Work: <?php echo $current_work; ?>
                        <?php if ($is_safe) { ?>
                            | Safe Sequence: <?php echo implode(' → ', $safe_sequence); ?>
                        <?php } ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: left; background-color: #fff3e0; padding: 15px; border-radius: 5px; border: 1px solid #ffcc80;">
            <h4>Detailed Safety Check Execution Log: (ขั้นตอนที่ไม่ติด Banker's Algorithm)</h4>
            <ul class="log-list">
                <?php 
                foreach ($execution_log as $log) {
                    $class = (strpos($log, 'ERROR') !== false || strpos($log, 'DEADLOCK') !== false) ? 'color: red; font-weight: bold;' : ((strpos($log, '✅') !== false) ? 'color: green; font-weight: bold;' : 'color: #333;');
                    echo "<li style='{$class}'>" . $log . "</li>";
                }
                ?>
            </ul>
        </div>

        <div class="button-group" style="justify-content: center;">
            <a href="index.php" class="calculate">Start New Simulation</a>
        </div>

        <?php } ?>

    </div>
</body>
</html>