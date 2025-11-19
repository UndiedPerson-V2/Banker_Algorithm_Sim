<?php
// Check if values are submitted
if (!isset($_POST['num_processes'], $_POST['initial_available'])) {
    header("Location: index.php");
    exit;
}

$num_processes = (int)$_POST['num_processes'];
$initial_available_str = trim($_POST['initial_available']);
$available_values = array_filter(array_map('trim', explode(' ', $initial_available_str)));
$num_resources = count($available_values);

if ($num_resources === 0) {
    echo "Error: Please enter at least one value for Available Resources.";
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Resource Mode</title>
    <style>
        body { font-family: sans-serif; background-color: #e6f7ff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        h2 { color: #333; margin-top: 0; }
        .info { margin-bottom: 20px; background-color: #f0f8ff; padding: 10px; border-radius: 4px; }
        .button-group button { 
            background-color: #4c8bf5; color: white; padding: 15px 30px; margin: 10px; 
            border: none; border-radius: 6px; cursor: pointer; font-size: 18px; width: 100%; 
        }
        .button-group button:hover { background-color: #3b74d3; }
        .back-link { display: block; margin-top: 20px; color: #f44336; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Step 2: Select Resource Simulation Mode</h2>
        <div class="info">
            <p>Number of Processes (n): **<?php echo $num_processes; ?>**</p>
            <p>Available Input: **<?php echo htmlspecialchars($initial_available_str); ?>** (Resource Types: **<?php echo $num_resources; ?>**)</p>
        </div>
        
        <form method="POST" id="mode_form">
            <input type="hidden" name="num_processes" value="<?php echo $num_processes; ?>">
            <input type="hidden" name="initial_available" value="<?php echo htmlspecialchars($initial_available_str); ?>">
            <input type="hidden" name="num_resources" value="<?php echo $num_resources; ?>">

            <div class="button-group">
                <button type="submit" name="mode_single" <?php echo ($num_resources > 1) ? 'disabled' : ''; ?>>
                    Single Resource Mode (m=1)
                </button>
                <?php if ($num_resources > 1) echo '<p style="color: red; font-size: 14px;">(Disabled: Input contains more than one resource value)</p>'; ?>

                <button type="submit" name="mode_multi" <?php echo ($num_resources === 1) ? 'disabled' : ''; ?>>
                    Multi-Resource Mode (m=<?php echo $num_resources; ?>)
                </button>
                 <?php if ($num_resources === 1) echo '<p style="color: red; font-size: 14px;">(Disabled: Input contains only one resource value)</p>'; ?>
            </div>
        </form>
        
        <a href="index.php" class="back-link">Go back to modify initial values</a>
    </div>

    <script>
        document.getElementById('mode_form').addEventListener('submit', function(e) {
            if (e.submitter.name === 'mode_single') {
                e.currentTarget.action = 'generate_table_single.php';
            } else if (e.submitter.name === 'mode_multi') {
                e.currentTarget.action = 'generate_table_multi.php';
            }
        });
    </script>
</body>
</html>