<?php
// Check if values are submitted via GET (Auto Redirect)
if (!isset($_GET['auto_mode'], $_GET['num_processes'], $_GET['initial_available'], $_GET['num_resources'])) {
    if (!isset($_POST['calculate'])) {
        header("Location: index.php");
        exit;
    }
}

// Determine source of data (GET or POST)
$is_initial_load = !isset($_POST['calculate']);
$source = $is_initial_load ? $_GET : $_POST;

$num_processes = (int)$source['num_processes'];
$num_resources = (int)$source['num_resources'];
$initial_available_str = trim($source['initial_available']);
$initial_work_array = array_map('intval', explode(' ', $initial_available_str));

$show_input_form = $is_initial_load;
$is_safe = false;
$is_invalid = false;
$safe_sequence = [];
$work = []; 
$final_work_display = "";
$execution_log = [];

$allocation = [];
$max = [];
$need = []; 
$finish = [];

if ($is_initial_load) {
    for ($p = 1; $p <= $num_processes; $p++) {
        for ($r = 1; $r <= $num_resources; $r++) {
            $allocation["P{$p}"]["R{$r}"] = 0;
            $max["P{$p}"]["R{$r}"] = 0;
        }
    }
}

// --- Helper Functions for Vector Comparison ---
$is_less_or_equal = function($vector_a, $vector_b, $R_count) {
    for ($i = 1; $i <= $R_count; $i++) {
        $key = "R" . $i;
        if ($vector_a[$key] > $vector_b[$key]) {
            return false;
        }
    }
    return true;
};

$vector_add = function($vector_a, $vector_b, $R_count) {
    $result = [];
    for ($i = 1; $i <= $R_count; $i++) {
        $key = "R" . $i;
        $result[$key] = $vector_a[$key] + $vector_b[$key];
    }
    return $result;
};
// --------------------------------------------------

if (isset($_POST['calculate'])) {
    // --- Calculation (Multi-Resource) ---
    $data = $_POST['data'];
    $allocation = $data['allocation'];
    $max = $data['max'];

    // 1. Calculate Need and Validate Input
    $need = [];
    $is_invalid = false;
    foreach ($allocation as $process => $alloc_row) {
        foreach ($alloc_row as $resource => $alloc_val) {
            $need_val = $max[$process][$resource] - $alloc_val;
            
            // Validation: Allocation cannot be greater than Max (Need < 0)
            if ($need_val < 0) {
                $execution_log[] = "❌❌ **Input Validation Error:** Process {$process} violates the Banker's rule (Allocation[{$resource}] ({$alloc_val}) > Max[{$resource}] ({$max[$process][$resource]})). Need is negative.";
                $is_invalid = true;
                break 2; 
            }
            $need[$process][$resource] = $need_val;
        }
    }
    
    if (!$is_invalid) {
        // 2. Initialize Work and Finish (Step 1)
        $work = [];
        for ($r = 1; $r <= $num_resources; $r++) {
            $work["R{$r}"] = $initial_work_array[$r - 1];
        }
        $finish = array_fill_keys(array_keys($allocation), false); 

        // Banker's Safety Algorithm
        $bankers_safety_algorithm = function() use (&$work, &$finish, &$allocation, &$need, &$safe_sequence, $num_processes, $is_less_or_equal, $vector_add, $num_resources, &$execution_log) {
            $count = 0; 
            $initial_work_snapshot = $work;

            $execution_log[] = "System Status: Start Safety Check.";
            $execution_log[] = "Initial Work: [" . implode(' ', $initial_work_snapshot) . "]";
            
            $safety_check_counter = 0;
            while ($count < $num_processes && $safety_check_counter < $num_processes + 1) {
                $found_process = false;
                $execution_log[] = "--- Cycle " . ($count + 1) . " (Current Work: [" . implode(' ', $work) . "]) ---";
                
                foreach ($finish as $process => $status) {
                    if ($status === false) {
                        
                        // Step 2: Need[i] <= Work (Vector Check)
                        $need_satisfied = $is_less_or_equal($need[$process], $work, $num_resources);
                        $need_display = "[" . implode(' ', $need[$process]) . "]";
                        
                        if ($need_satisfied) {
                            
                            // Step 3: Simulate Execution (Non-Blocked Step)
                            $work_before = $work;
                            $work = $vector_add($work, $allocation[$process], $num_resources);
                            
                            $finish[$process] = true;
                            $safe_sequence[] = $process;
                            $count++;
                            $found_process = true;
                            $safety_check_counter = 0;
                            
                            $alloc_display = "[" . implode(' ', $allocation[$process]) . "]";
                            
                            // Log successful step
                            $execution_log[] = "✅ Process **{$process}** can finish because Need {$need_display} **&le;** Work [" . implode(' ', $work_before) . "].";
                            $execution_log[] = "   New Work = Work + Allocation: [" . implode(' ', $work_before) . "] + {$alloc_display} = **[" . implode(' ', $work) . "]**";
                            
                            break; 
                        } else {
                            // Log blocked step (in this specific iteration)
                            $execution_log[] = "❌ Process {$process} cannot finish: Need {$need_display} **&notle;** Work [" . implode(' ', $work) . "].";
                        }
                    }
                }
                
                if (!$found_process && $count < $num_processes) {
                    // Capture details of blocked processes
                    $blocked_processes = [];
                    foreach ($finish as $process => $status) {
                        if ($status === false) {
                             $blocked_processes[] = "{$process} (Need: [" . implode(' ', $need[$process]) . "])";
                        }
                    }
                    
                    $execution_log[] = "💔 **DEADLOCK DETECTED (UNSAFE STATE)** at Cycle " . ($count + 1) . ".";
                    $execution_log[] = "   Current Work (Available): [" . implode(' ', $work) . "]";
                    $execution_log[] = "   Blocked Processes: " . implode(', ', $blocked_processes) . " (Cannot satisfy Need **&le;** Work)";
                    return false; 
                }
                
                $safety_check_counter++;
            }
            
            $execution_log[] = "System Status: All {$num_processes} processes finished successfully.";
            return true;
        };

        $is_safe = $bankers_safety_algorithm();
    } // End if (!is_invalid)
    
    $final_work_display = implode(' ', $work);
    $show_input_form = false; 
}


// ** Pre-calculate Need Matrix for Display (used when $show_input_form is true) **
$display_need_matrix = [];
for ($p = 1; $p <= $num_processes; $p++) {
    for ($r = 1; $r <= $num_resources; $r++) {
        $r_key = "R" . $r;
        $p_key = "P" . $p;
        $alloc_val = isset($allocation[$p_key][$r_key]) ? $allocation[$p_key][$r_key] : 0;
        $max_val = isset($max[$p_key][$r_key]) ? $max[$p_key][$r_key] : 0;
        
        $display_need_matrix[$p_key][$r_key] = max(0, $max_val - $alloc_val);
    }
}


// ** Lambda Function for Manual Input Field (Allocation and Max) **
$create_input_field = function($p_index, $r_index, $type) use ($allocation, $max) {
    $name = "data[{$type}][P{$p_index}][R{$r_index}]";
    $value_matrix = ($type === 'allocation') ? $allocation : $max;
    $r_key = "R" . $r_index;
    $p_key = "P" . $p_index;
    // ใช้ ?? 0 เพื่อป้องกัน Warning เมื่อคีย์ไม่มีอยู่
    $value = $value_matrix[$p_key][$r_key] ?? '0'; 
    return "<td><input type='number' name='{$name}' min='0' value='{$value}' required style='width: 50px;'></td>";
};

// ** Lambda Function for Disabled Need Field **
$create_disabled_need_field = function($p_index, $r_index, $display_need_matrix) {
    $r_key = "R" . $r_index;
    $p_key = "P" . $p_index;
    $value = $display_need_matrix[$p_key][$r_key] ?? 0;
    return "<td><input type='text' value='{$value}' disabled style='width: 50px; background-color: #eee; color: #666;'></td>";
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Banker's Algorithm Multi-Resource</title>
    <style>
        body { font-family: sans-serif; background-color: #e6f7ff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 1000px; }
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
            <h2>Banker's Algorithm Multi-Resource Mode</h2>
            <p><?php echo $show_input_form ? "Step 2: Enter Allocation and Max Matrices (n={$num_processes}, m={$num_resources})" : "Calculation Result"; ?></p>
        </div>
        
        <?php if ($show_input_form) { ?>
        <form method="POST">
            <input type="hidden" name="num_processes" value="<?php echo $num_processes; ?>">
            <input type="hidden" name="num_resources" value="<?php echo $num_resources; ?>">
            <input type="hidden" name="initial_available" value="<?php echo htmlspecialchars($initial_available_str); ?>">
            
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">Process</th>
                        <th colspan="<?php echo $num_resources; ?>">Allocation</th>
                        <th colspan="<?php echo $num_resources; ?>">Need (Calculated)</th> 
                        <th colspan="<?php echo $num_resources; ?>">Max</th>
                    </tr>
                    <tr>
                        <?php 
                        for ($r = 1; $r <= $num_resources; $r++) { echo "<th>R{$r}</th>"; } 
                        for ($r = 1; $r <= $num_resources; $r++) { echo "<th>R{$r}</th>"; } 
                        for ($r = 1; $r <= $num_resources; $r++) { echo "<th>R{$r}</th>"; } 
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    for ($p = 1; $p <= $num_processes; $p++) { 
                        echo "<tr>";
                        echo "<td>P{$p}</td>";
                        
                        // Allocation Inputs (Manual)
                        for ($r = 1; $r <= $num_resources; $r++) { echo $create_input_field($p, $r, 'allocation'); }
                        
                        // Need Display Inputs (Disabled/Calculated)
                        for ($r = 1; $r <= $num_resources; $r++) { echo $create_disabled_need_field($p, $r, $display_need_matrix); }
                        
                        // Max Inputs (Manual)
                        for ($r = 1; $r <= $num_resources; $r++) { echo $create_input_field($p, $r, 'max'); }
                        
                        echo "</tr>";
                    } 
                    ?>
                </tbody>
            </table>
            
            <div style="margin-top: 15px; font-weight: bold;">
                Initial Available Vector: **<?php echo htmlspecialchars($initial_available_str); ?>**
            </div>

            <div class="button-group">
                <button type="submit" name="calculate" class="calculate">Calculate System State</button>
                <a href="index.php" class="back">Back to Initial Setup</a>
            </div>
            
            <p class="note">Enter Allocation and Max values manually. The Need Vector (Max - Allocation) is displayed but cannot be edited. Ensure Allocation $\le$ Max for all resources.</p>
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
                    <th rowspan="2">Process</th>
                    <th colspan="<?php echo $num_resources; ?>">Allocation</th>
                    <th colspan="<?php echo $num_resources; ?>">Need</th>
                    <th colspan="<?php echo $num_resources; ?>">Max</th>
                    <th rowspan="2">Finish</th>
                </tr>
                <tr>
                    <?php 
                    for ($r = 1; $r <= $num_resources; $r++) { echo "<th>R{$r}</th>"; } 
                    for ($r = 1; $r <= $num_resources; $r++) { echo "<th>R{$r}</th>"; } 
                    for ($r = 1; $r <= $num_resources; $r++) { echo "<th>R{$r}</th>"; } 
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($allocation as $process => $alloc_row) {
                    echo "<tr>";
                    echo "<td>{$process}</td>";
                    foreach ($alloc_row as $val) { echo "<td>{$val}</td>"; }
                    foreach ($need[$process] as $val) { echo "<td>{$val}</td>"; }
                    foreach ($max[$process] as $val) { echo "<td>{$val}</td>"; }
                    
                    $final_status = $finish[$process] ? 'True' : 'False';
                    $class = $finish[$process] ? 'finish-true' : 'finish-false';
                    echo "<td class='{$class}'>{$final_status}</td>";
                    echo "</tr>";
                }
                ?>
                <tr>
                    <td colspan="<?php echo 3 * $num_resources + 2; ?>" style="text-align: left; font-weight: bold;">
                        Initial Available Vector: **<?php echo htmlspecialchars($initial_available_str); ?>**
                        <?php if ($is_safe) { ?>
                            | Final Work Vector: **<?php echo $final_work_display; ?>**
                            | Safe Sequence: **<?php echo implode(' → ', $safe_sequence); ?>**
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