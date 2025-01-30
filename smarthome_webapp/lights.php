<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">   <!-- boiler plate-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Light</title>
    <?php 
    $pageTitle = "Light Control";
    include 'common_styles.php'; // Including css styling for the header
    ?>
    <style>
        .light-container { /* setting container for light control to align elemts in column and be centered */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .light-status { /* text*/
            font-size: 24px;
            margin: 20px 0;
        }

        .light-btn { /*button styling*/
            padding: 20px 40px;
            font-size: 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .light-btn.on { /*button styling dependent on on status*/
            background-color: #f1c40f;     
            box-shadow: 0 0 20px rgba(241, 196, 15, 0.5);  
        }

        .light-btn.off { /*button styling dependent on off status*/
            background-color: #95a5a6;     
        }

        .bulb-icon { /*text styling for bulb emoji*/
            font-size: 200px;
            margin: 20px 0;
            opacity: 0.3;            /*opacity changes to idnicate on or off, initializes as off*/       
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; //includes header javascript ?> 

    <div class="light-container"> 
        <div class="bulb-icon" id="bulb">💡 <!-- Text emoji as light--></div>
        <div class="light-status" id="status">Light is OFF<!-- initialize light as off--></div> 
        <button class="light-btn off" id="toggleButton" onclick="toggleLight()"> <!--initialize toggle button with text turn on-->
            Turn ON
        </button>
    </div>

    <script>
        let isLightOn = false; // here will be able to adjust load the database value
        //Get elements 
        const button = document.getElementById('toggleButton');
        const status = document.getElementById('status');
        const bulb = document.getElementById('bulb');

        function toggleLight() { //when toggled change lightbulb status, opacity of bulb, and relevent text
            isLightOn = !isLightOn;   
            
            if (isLightOn) {
                button.textContent = 'Turn OFF';
                button.classList.remove('off');
                button.classList.add('on');
                status.textContent = 'Light is ON';
                bulb.style.opacity = '1';
            } else {
                button.textContent = 'Turn ON';
                button.classList.remove('on');
                button.classList.add('off');
                status.textContent = 'Light is OFF';
                bulb.style.opacity = '0.3';
            }
        }
    </script>
</body>
</html>
