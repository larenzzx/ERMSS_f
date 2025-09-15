<?php
// Include your database connection code here
require_once('../db.connection/connection.php');

// Fetch distinct years from the Events table
$yearQuery = "SELECT DISTINCT YEAR(date_start) AS event_year FROM Events ORDER BY event_year DESC";
$yearResult = mysqli_query($conn, $yearQuery);
$years = mysqli_fetch_all($yearResult, MYSQLI_ASSOC);

// Fetch total events excluding cancelled events (where event_cancel or cancelReason is not empty)
$totalEventsQuery = "
    SELECT COUNT(*) AS totalEvents 
    FROM events 
    WHERE (event_cancel IS NULL OR event_cancel = '') 
    AND (cancelReason IS NULL OR cancelReason = '')
";
$totalEventsResult = mysqli_query($conn, $totalEventsQuery);
$totalEvents = mysqli_fetch_assoc($totalEventsResult)['totalEvents'];


// Fetch total upcoming events (excluding cancelled events)
$totalUpcomingQuery = "SELECT COUNT(*) AS totalUpcoming FROM Events WHERE (NOW() < CONCAT(date_start, ' ', time_start)) AND (event_cancel IS NULL OR event_cancel = '')";
$totalUpcomingResult = mysqli_query($conn, $totalUpcomingQuery);
$totalUpcoming = mysqli_fetch_assoc($totalUpcomingResult)['totalUpcoming'];

// Fetch total ongoing events (excluding cancelled events)
$totalOngoingQuery = "SELECT COUNT(*) AS totalOngoing FROM Events WHERE (NOW() BETWEEN CONCAT(date_start, ' ', time_start) AND CONCAT(date_end, ' ', time_end)) AND (event_cancel IS NULL OR event_cancel = '')";
$totalOngoingResult = mysqli_query($conn, $totalOngoingQuery);
$totalOngoing = mysqli_fetch_assoc($totalOngoingResult)['totalOngoing'];

// Fetch total ended events (excluding cancelled events)
$totalEndedQuery = "SELECT COUNT(*) AS totalEnded FROM Events WHERE (NOW() > CONCAT(date_end, ' ', time_end)) AND (event_cancel IS NULL OR event_cancel = '')";
$totalEndedResult = mysqli_query($conn, $totalEndedQuery);
$totalEnded = mysqli_fetch_assoc($totalEndedResult)['totalEnded'];



require('../fpdf186/fpdf.php');

if (isset($_GET['download'])) { 
    $selectedYear = $_GET['year'];
    $selectedMonth = $_GET['month'] ?? null;

    // Fetch events based on selected year and month
    $eventsQuery = "SELECT * FROM events WHERE YEAR(date_start) = ?";
    $params = [$selectedYear];

    if ($selectedMonth) {
        $eventsQuery .= " AND MONTH(date_start) = ?";
        $params[] = $selectedMonth;
    }

    $stmt = $conn->prepare($eventsQuery);
    $stmt->bind_param(str_repeat('i', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = $result->fetch_all(MYSQLI_ASSOC);

    // Create PDF document
    $pdf = new FPDF('P');
    $pdf->AddPage();

    // Add header with images and title
    $pdf->Image('img/wesmaarrdec-removebg-preview.png', 10, 8, 30); 
    $pdf->Image('img/wmsu_logo.png', 170, 8, 30); 
    // Set smaller font for the title and center the text
    // Add title in the center
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(5, 15); // Adjust Y position
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 10, 'Western Mindanao Agriculture, 
    Aquatic and Natural Resources Research and 
    Development Consortium 
    (WESMAARRDEC)', 0, 'C');
    $pdf->Ln(10);
  

    // Set title and header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'Events Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Ln(10);

    // Display selected filters
    $pdf->Cell(0, 10, 'Year: ' . $selectedYear, 0, 1);
    if ($selectedMonth) {
        $pdf->Cell(0, 10, 'Month: ' . date('F', mktime(0, 0, 0, $selectedMonth, 10)), 0, 1);
    }
    $pdf->Ln(10);

    // Table Header
    $pdf->SetFont('helvetica', 'B', 12);
    $header = [
        'Event Name' => 60,
        'Start Date' => 30,
        'End Date' => 30,
        'Status' => 30
    ];

    // Print header
    foreach ($header as $title => $width) {
        $pdf->Cell($width, 10, $title, 1, 0, 'C'); // Center header
    }
    $pdf->Ln();

    // Check if there are events and output content accordingly
    if (empty($events)) {
        // No events found
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->Cell(0, 10, 'No events found!', 0, 1, 'C');
    } else {
        // Table Content
        $pdf->SetFont('helvetica', '', 12);

        date_default_timezone_set('Asia/Manila'); // Set timezone to Manila
        $currentDate = date('Y-m-d');

        $eventCount = 0; // Initialize event counter
        foreach ($events as $event) {
            // Add a new page if the count exceeds 20
            if ($eventCount == 20) {
                $pdf->AddPage();
                // Print header again on the new page
                foreach ($header as $title => $width) {
                    $pdf->Cell($width, 10, $title, 1, 0, 'C'); // Center header
                }
                $pdf->Ln();
                $eventCount = 0; // Reset counter
            }

            $eventTitleWidth = max(60, $pdf->GetStringWidth($event['event_title']) + 4); // +4 for padding
            $pdf->Cell($eventTitleWidth, 10, $event['event_title'], 1);
            $pdf->Cell(30, 10, $event['date_start'], 1, 0, 'C'); // Center content
            $pdf->Cell(30, 10, $event['date_end'], 1, 0, 'C'); // Center content

            // Determine event status
            if ($event['event_cancel'] !== null && $event['event_cancel'] !== '') {
                $status = 'Cancelled'; // Event is cancelled
            } elseif ($event['date_start'] <= $currentDate && $event['date_end'] >= $currentDate) {
                $status = 'Ongoing'; // Ongoing event
            } elseif ($event['date_end'] < $currentDate) {
                $status = 'Ended'; // Ended event
            } else {
                $status = 'Upcoming'; // Upcoming event
            }

            $pdf->Cell(30, 10, $status, 1, 0, 'C'); // Center content
            $pdf->Ln();
            $eventCount++; // Increment the event counter
        }
    }

    // Output PDF
    $pdf->Output('events_report.pdf', 'D');
    exit();
}


?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE-edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management System</title>

    <!--boxicons-->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!--browser icon-->
    <link rel="icon" href="img/wesmaarrdec.jpg" type="image/png">

    <link rel="stylesheet" href="css/main.css">


</head>

<body>

    <?php
    session_start();
    require_once('../db.connection/connection.php');

    function showProfileModal($message)
    {
        echo "<script>
                          showModal('$message');
                      </script>";
    }
    function countPendingUsers($conn)
    {
        $sqls = "SELECT COUNT(*) AS totalPendingUsers FROM pendinguser";
        $result = $conn->query($sqls);

        if ($result) {
            $row = $result->fetch_assoc();
            return $row['totalPendingUsers'];
        } else {
            return 0; // Return 0 if there is an error or no pending users
        }
    }

    $pendingUsersCount = countPendingUsers($conn);
    function countPendingEvents($conn)
    {
        $sqls = "SELECT COUNT(*) AS totalPendingEvents FROM pendingevents";
        $result = $conn->query($sqls);

        if ($result) {
            $row = $result->fetch_assoc();
            return $row['totalPendingEvents'];
        } else {
            return 0;
        }
    }

    $pendingEventsCount = countPendingEvents($conn);


    function getAdminData($conn, $AdminID)
    {
        $sql = "SELECT * FROM admin WHERE AdminID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $AdminID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        } else {
            $stmt->close();
            return false;
        }
    }

    $AdminID = $_SESSION['AdminID']; // Retrieve AdminID from the session
    $adminData = getAdminData($conn, $AdminID);

    if ($adminData) {
        // Retrieve existing admin data
        $LastName = $adminData['LastName'];
        $FirstName = $adminData['FirstName'];
        $MI = $adminData['MI'];
        $Gender = $adminData['Gender'];
        $Email = $adminData['Email'];
        $ContactNo = $adminData['ContactNo'];
        $Address = $adminData['Address'];
        $Affiliation = $adminData['Affiliation'];
        $Position = $adminData['Position'];
        $Image = isset($adminData['Image']) ? $adminData['Image'] : null;
    } else {
        // Redirect or handle error if admin data is not found
        showProfileModal("Admin data not found");
        exit();
    }

    ?>

    <!-- ====SIDEBAR==== -->
    <div class="sidebar">
        <div class="top">
            <div class="logo">
                <img src="img/wesmaarrdec-removebg-preview.png" alt="">
                <span>WESMAARRDEC</span>
            </div>
            <i class="bx bx-menu" id="btnn"></i>
        </div>
        <div class="user">

            <?php if (!empty($Image)): ?>
                <img src="../assets/img/profilePhoto/<?php echo $Image; ?>" alt="user" class="user-img">
            <?php else: ?>
                <img src="../assets/img/profile.jpg" alt="default user" class="user-img">
            <?php endif; ?>
            <div>
                <p class="bold"><?php echo $FirstName . ' ' . $MI . ' ' . $LastName; ?></p>
                <p><?php echo $Position; ?></p>
            </div>
        </div>


        <ul>
            <li class="nav-sidebar">
                <a href="adminDashboard.php">
                    <i class="bx bxs-grid-alt"></i>
                    <span class="nav-item">Dashboard</span>
                </a>
                <span class="tooltip">Dashboard</span>
            </li>

            <li class="events-side2 nav-sidebar">
                <a href="#" class="a-events">
                    <i class='bx bx-archive'></i>
                    <span class="nav-item">Events</span>
                    <i class='bx bx-chevron-down hide'></i>
                </a>
                <span class="tooltip">Events</span>
                <div class="uno">
                    <ul>
                        <?php if ($_SESSION['Role'] === 'superadmin') { ?>
                            <a href="eventsValidation.php">Events Validation
                                <span><?php echo $pendingEventsCount; ?></span></a>
                        <?php } elseif ($_SESSION['Role'] === 'Admin') { ?>
                            <a href="pendingEvents.php">Pending Events <span><?php echo $pendingEventsCount; ?></span></a>
                        <?php } ?>
                        <a href="landingPage.php">Events</a>
                        <a href="addEvent.php">Add Event</a>
                        <a href="addEventTypeMode.php">Event Settings</a>
                        <a href="history.php">History</a>
                        <a href="cancelEvent.php">Cancelled</a>
                    </ul>
                </div>
            </li>

            <li class="events-side nav-sidebar">
                <a href="#" class="a-events">
                    <i class='bx bx-user'></i>
                    <span class="nav-item">Account</span>
                    <i class='bx bx-chevron-down hide'></i>
                </a>
                <span class="tooltip">Account</span>
                <div class="uno">
                    <ul>
                        <a href="profile.php">My Profile</a>
                        <a href="validation.php">User Validation <span><?php echo $pendingUsersCount; ?></span></a>
                        <a href="newAccount.php">Create Account</a>
                        <a href="allUser.php">All Users</a>
                    </ul>
                </div>
            </li>

            <li class="nav-sidebar">
                <a href="#" onclick="confirmLogout(event)">
                    <i class="bx bx-log-out"></i>
                    <span class="nav-item">Logout</span>
                </a>
                <span class="tooltip">Logout</span>
            </li>

            <script>
                function confirmLogout(event) {
                    event.preventDefault();

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You will be logged out.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, logout'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "../login.php";
                        }
                    });
                }
            </script>
        </ul>
    </div>

    <!-- ============ CONTENT ============-->
    <div class="main-content">

        <div class="containerr">
            <h3 class="dashboard apply">EVENT MANAGEMENT SYSTEM</h3>

            <section class="category">

                <div class="box-container">

                    <a href="total_events.php" class="box">
                        <i class='bx bx-archive'></i>
                        <div>
                            <h3>Total</h3>
                            <span><?php echo $totalEvents; ?> Events</span>
                        </div>
                    </a>

                    <a href="upcoming_events.php" class="box">
                        <i class='bx bx-archive'></i>
                        <div>
                            <h3>Upcoming</h3>
                            <span><?php echo $totalUpcoming; ?> Events</span>
                        </div>
                    </a>

                    <a href="ongoing_events.php" class="box">
                        <i class='bx bx-archive'></i>
                        <div>
                            <h3>Ongoing</h3>
                            <span><?php echo $totalOngoing; ?> Events</span>
                        </div>
                    </a>

                    <a href="ended_events.php" class="box">
                        <i class='bx bx-archive'></i>
                        <div>
                            <h3>Completed</h3>
                            <span><?php echo $totalEnded; ?> Events</span>
                        </div>
                    </a>

                    <!-- <a href="sponsors.php" class="box">
                        <i class='bx bx-archive'></i>
                        <div>
                            <h3>Sponsors</h3>
                            <span><?php echo $totalEnded; ?> Events</span>
                        </div>
                    </a> -->
                </div>
            </section>

            <!--charts-->
            <div class="graphBox">
                <div class="box">
                    <div>
                        <select name="selectedYear" id="selectedYear">
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year['event_year']; ?>"><?php echo $year['event_year']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <canvas id="myChart"></canvas>
                    </div>
                </div>
                <div class="box">
                    <canvas id="eventsYear"></canvas>
                </div>
            </div>

            <div class="graphBox_alt">
                <?php
                $query = "SELECT DISTINCT YEAR(date_start) AS year FROM events ORDER BY year DESC";
                $result = $conn->query($query);
                ?>

                <div class="event_report_download">
                    <div>
                        <select id="yearSelect">
                            <option value="" disabled selected>Select Year</option>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . $row['year'] . '">' . $row['year'] . '</option>';
                                }
                            }
                            ?>
                        </select>

                        <select id="monthSelect">
                            <option value="" disabled selected>Select Month</option>
                            <option value="all">All Months</option>
                            <option value="01">January</option>
                            <option value="02">February</option>
                            <option value="03">March</option>
                            <option value="04">April</option>
                            <option value="05">May</option>
                            <option value="06">June</option>
                            <option value="07">July</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>

                    <button onclick="downloadReport()" class="download-button">
                        <i class='fa fa-print'></i> Download Report
                    </button>
                </div>
            </div>

        </div>

    </div>




    <script>
        function downloadReport() {
            const selectedYear = document.getElementById('yearSelect').value;
            const selectedMonth = document.getElementById('monthSelect').value;

            if (selectedYear && selectedMonth === 'all') {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "Do you want to download the report for the selected year?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, download it!',
                    customClass: {
                        popup: 'larger-swal'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `?download=true&year=${selectedYear}`;
                    }
                });
            } else if (selectedYear && selectedMonth) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "Do you want to download the report for the selected year and month?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, download it!',
                    customClass: {
                        popup: 'larger-swal'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `?download=true&year=${selectedYear}&month=${selectedMonth}`;
                    }
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: "Please select a year/month.",
                    icon: 'warning',
                    customClass: {
                        popup: 'larger-swal'
                    }
                });
            }
        }
    </script>




    <!-- CONFIRM DELETE -->
    <script src=js/deleteEvent.js></script>


    <!--JS -->
    <script src="js/eventscript.js"></script>


    <!--sidebar functionality-->
    <script src="js/sidebar.js"></script>

    <!--chart js-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <!-- <script src="js/myChart.js"></script> -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Chart instances
            let barChart = null;
            let lineChart = null;
            
            // Chart contexts
            const barCtx = document.getElementById('myChart').getContext('2d');
            const lineCtx = document.getElementById('eventsYear').getContext('2d');
            const selectedYearElement = document.getElementById('selectedYear');
            
            let selectedYearValue = selectedYearElement.value;
            
            // Chart.js responsive configuration
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 11
                            },
                            padding: 8
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 11
                            },
                            padding: 8
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    point: {
                        radius: 4,
                        hoverRadius: 6,
                        borderWidth: 2
                    },
                    line: {
                        borderWidth: 3,
                        tension: 0.3
                    }
                },
                animation: {
                    duration: 800,
                    easing: 'easeInOutQuart'
                }
            };
            
            // Enhanced color schemes
            const barColors = [
                'rgba(99, 102, 241, 0.8)',   // Indigo
                'rgba(16, 185, 129, 0.8)',   // Emerald
                'rgba(245, 158, 11, 0.8)',   // Amber
                'rgba(239, 68, 68, 0.8)',    // Red
                'rgba(168, 85, 247, 0.8)',   // Purple
                'rgba(14, 165, 233, 0.8)',   // Sky
                'rgba(34, 197, 94, 0.8)',    // Green
                'rgba(251, 146, 60, 0.8)',   // Orange
                'rgba(236, 72, 153, 0.8)',   // Pink
                'rgba(59, 130, 246, 0.8)',   // Blue
                'rgba(139, 92, 246, 0.8)',   // Violet
                'rgba(6, 182, 212, 0.8)'     // Cyan
            ];
            
            const barBorderColors = [
                'rgb(99, 102, 241)',   'rgb(16, 185, 129)',   'rgb(245, 158, 11)',
                'rgb(239, 68, 68)',    'rgb(168, 85, 247)',   'rgb(14, 165, 233)',
                'rgb(34, 197, 94)',    'rgb(251, 146, 60)',   'rgb(236, 72, 153)',
                'rgb(59, 130, 246)',   'rgb(139, 92, 246)',   'rgb(6, 182, 212)'
            ];
            
            // Wrap canvas elements for proper responsiveness
            function wrapCanvasElements() {
                const canvases = document.querySelectorAll('.graphBox canvas');
                canvases.forEach(canvas => {
                    if (!canvas.parentElement.classList.contains('chart-wrapper')) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'chart-wrapper';
                        canvas.parentNode.insertBefore(wrapper, canvas);
                        wrapper.appendChild(canvas);
                    }
                });
            }
            
            // Add loading state
            function showChartLoading(container) {
                const loadingEl = document.createElement('div');
                loadingEl.className = 'chart-loading';
                loadingEl.innerHTML = '<span>Loading chart...</span>';
                container.appendChild(loadingEl);
                return loadingEl;
            }
            
            function hideChartLoading(loadingEl) {
                if (loadingEl && loadingEl.parentNode) {
                    loadingEl.parentNode.removeChild(loadingEl);
                }
            }
            
            // Year selector change handler
            selectedYearElement.addEventListener('change', function () {
                selectedYearValue = selectedYearElement.value;
                updateCharts();
            });
            
            // Resize observer for responsive handling
            const resizeObserver = new ResizeObserver((entries) => {
                entries.forEach(entry => {
                    // Delay chart resize to allow container to fully adjust
                    setTimeout(() => {
                        if (barChart) barChart.resize();
                        if (lineChart) lineChart.resize();
                    }, 100);
                });
            });
            
            // Observe chart containers
            const graphBoxes = document.querySelectorAll('.graphBox .box');
            graphBoxes.forEach(box => resizeObserver.observe(box));
            
            // Chart update functions
            function updateCharts() {
                const barContainer = barCtx.canvas.parentElement;
                const lineContainer = lineCtx.canvas.parentElement;
                
                const barLoading = showChartLoading(barContainer);
                const lineLoading = showChartLoading(lineContainer);
                
                // Fetch data for both charts
                Promise.all([
                    fetchBarChartData(selectedYearValue),
                    fetchLineChartData()
                ]).then(([barData, lineData]) => {
                    updateBarChart(barData);
                    updateLineChart(lineData);
                    
                    hideChartLoading(barLoading);
                    hideChartLoading(lineLoading);
                }).catch(error => {
                    console.error('Error updating charts:', error);
                    hideChartLoading(barLoading);
                    hideChartLoading(lineLoading);
                });
            }
            
            function fetchBarChartData(year) {
                return fetch('../function/F.getMonthlyEvents.php?year=' + year)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => data.events);
            }
            
            function fetchLineChartData() {
                return fetch('../function/F.getYearlyEvents.php')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => data.events);
            }
            
            function updateBarChart(data) {
                if (barChart) {
                    barChart.destroy();
                }
                
                barChart = new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: [
                            'January', 'February', 'March', 'April', 
                            'May', 'June', 'July', 'August', 
                            'September', 'October', 'November', 'December'
                        ],
                        datasets: [{
                            label: `Events in ${selectedYearValue}`,
                            data: data.map(monthData => monthData.total_events),
                            backgroundColor: barColors,
                            borderColor: barBorderColors,
                            borderWidth: 2,
                            borderRadius: 4,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        ...chartOptions,
                        plugins: {
                            ...chartOptions.plugins,
                            title: {
                                display: true,
                                text: `Monthly Events Distribution - ${selectedYearValue}`,
                                font: {
                                    size: 14,
                                    weight: '600'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 30
                                }
                            }
                        },
                        scales: {
                            ...chartOptions.scales,
                            y: {
                                ...chartOptions.scales.y,
                                title: {
                                    display: true,
                                    text: 'Number of Events',
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                ...chartOptions.scales.x,
                                title: {
                                    display: true,
                                    text: 'Months',
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            function updateLineChart(data) {
                if (lineChart) {
                    lineChart.destroy();
                }
                
                lineChart = new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: data.map(yearData => yearData.year),
                        datasets: [{
                            label: 'Total Events per Year',
                            data: data.map(yearData => yearData.total_events),
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: 'rgb(99, 102, 241)',
                            pointBorderColor: 'white',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                        }]
                    },
                    options: {
                        ...chartOptions,
                        plugins: {
                            ...chartOptions.plugins,
                            title: {
                                display: true,
                                text: 'Yearly Events Trend',
                                font: {
                                    size: 14,
                                    weight: '600'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 30
                                }
                            }
                        },
                        scales: {
                            ...chartOptions.scales,
                            y: {
                                ...chartOptions.scales.y,
                                title: {
                                    display: true,
                                    text: 'Total Events',
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                ...chartOptions.scales.x,
                                title: {
                                    display: true,
                                    text: 'Years',
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Handle sidebar toggle effects
            function handleSidebarToggle() {
                const sidebar = document.querySelector('.sidebar');
                if (sidebar) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                                // Delay chart resize to allow sidebar animation to complete
                                setTimeout(() => {
                                    if (barChart) barChart.resize();
                                    if (lineChart) lineChart.resize();
                                }, 350); // Match sidebar transition duration
                            }
                        });
                    });
                    
                    observer.observe(sidebar, {
                        attributes: true,
                        attributeFilter: ['class']
                    });
                }
            }
            
            // Initialize everything
            function initialize() {
                wrapCanvasElements();
                handleSidebarToggle();
                updateCharts();
            }
            
            // Start initialization
            initialize();
            
            // Handle window resize with debouncing
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    if (barChart) barChart.resize();
                    if (lineChart) lineChart.resize();
                }, 150);
            });
        });

    </script>

</body>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tblWrapper = document.querySelector('.tbl-wrapper');
        const tblHead = document.querySelector('.tbl thead');

        tblWrapper.addEventListener('scroll', function () {
            const scrollLeft = tblWrapper.scrollLeft;
            const thElements = tblHead.getElementsByTagName('th');

            for (let th of thElements) {
                th.style.left = `-${scrollLeft}px`;
            }
        });
    });
</script>




<!--real-time update-->
<script src="js/realTimeUpdate.js"></script>

</html>