<?php
session_start();
include('../function/F.event_retrieve.php');
$eventId = isset($_GET['event_id']) ? $_GET['event_id'] : null;

// Fetch total number of cancelled events
$countCancelledEventsSql = "SELECT COUNT(*) AS totalCancelledEvents FROM Events WHERE event_cancel IS NOT NULL AND event_cancel <> ''";
$countCancelledEventsResult = mysqli_query($conn, $countCancelledEventsSql);
$countCancelledEventsRow = mysqli_fetch_assoc($countCancelledEventsResult);
$totalCancelledEvents = $countCancelledEventsRow['totalCancelledEvents'];

// Fetch total participants for the specified event title
$totalParticipantsSql = "SELECT COUNT(*) AS totalParticipants FROM eventParticipants WHERE event_id = ?";
$totalParticipantsStmt = $conn->prepare($totalParticipantsSql);
$totalParticipantsStmt->bind_param("i", $eventId);
$totalParticipantsStmt->execute();
$totalParticipantsResult = $totalParticipantsStmt->get_result();
$totalParticipantsRow = $totalParticipantsResult->fetch_assoc();
$totalParticipants = $totalParticipantsRow['totalParticipants'];

// Fetch participant limit for the event
$participantLimitSql = "SELECT participant_limit FROM Events WHERE event_id = ?";
$participantLimitStmt = $conn->prepare($participantLimitSql);
$participantLimitStmt->bind_param("i", $eventId);
$participantLimitStmt->execute();
$participantLimitResult = $participantLimitStmt->get_result();
$participantLimitRow = $participantLimitResult->fetch_assoc();
$participantLimit = $participantLimitRow['participant_limit'];

// Calculate the ratio of total participants to participant limit
$participantRatio = $totalParticipants . "/" . $participantLimit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once('../db.connection/connection.php');

    if (isset($_SESSION['UserID'])) {
        $UserID = $_SESSION['UserID'];
        $eventId = isset($_POST['event_id']) ? $_POST['event_id'] : null;

        // Check if the user previously canceled the event
        if (hasCanceledEvent($conn, $eventId, $UserID)) {
            $_SESSION['error'] = 'You cannot rejoin this event since you previously canceled your participation.';
            header("Location: view_event.php?event_id=$eventId");
            exit();
        }

        if ($totalParticipants >= $participantLimit) {
            $_SESSION['error'] = 'Sorry, the participant limit for this event has been reached. You cannot join at the moment.';
            header("Location: view_event.php?event_id=$eventId");
            exit();
        }

        // Fetch event details to check status
        $sqlEventDetails = "SELECT * FROM Events WHERE event_id = ?";
        $stmtEventDetails = $conn->prepare($sqlEventDetails);
        $stmtEventDetails->bind_param("i", $eventId);
        $stmtEventDetails->execute();
        $resultEventDetails = $stmtEventDetails->get_result();

        if ($resultEventDetails->num_rows > 0) {
            $eventDetails = $resultEventDetails->fetch_assoc();
            $eventStatus = '';

            $eventTimeZone = new DateTimeZone('Asia/Manila');
            $currentDateTime = new DateTime('now', $eventTimeZone);
            $eventStartDateTime = new DateTime($eventDetails['date_start'] . ' ' . $eventDetails['time_start'], $eventTimeZone);
            $eventEndDateTime = new DateTime($eventDetails['date_end'] . ' ' . $eventDetails['time_end'], $eventTimeZone);

            if ($currentDateTime >= $eventStartDateTime && $currentDateTime <= $eventEndDateTime) {
                $eventStatus = 'ongoing';
            } elseif ($currentDateTime < $eventStartDateTime) {
                $eventStatus = 'upcoming';
            } else {
                $eventStatus = 'ended';
            }

            if ($eventStatus === 'upcoming') {
                $sqlCheck = "SELECT * FROM EventParticipants WHERE event_id = ? AND UserID = ?";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->bind_param("ii", $eventId, $UserID);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();

                if ($resultCheck->num_rows > 0) {
                    $_SESSION['error'] = 'You have already joined this event!';
                } else {
                    $sqlInsert = "INSERT INTO EventParticipants (event_id, UserID) VALUES (?, ?)";
                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->bind_param("ii", $eventId, $UserID);

                    if ($stmtInsert->execute()) {
                        $_SESSION['success'] = 'Successfully joined the event!';
                    } else {
                        $_SESSION['error'] = 'Failed to join the event. Please try again.';
                    }

                    $stmtInsert->close();
                }

                $stmtCheck->close();
            } else {
                $_SESSION['error'] = 'Unfortunately, participation in this event is no longer available as it has already commenced.';
            }
        } else {
            $_SESSION['error'] = 'Event details not found.';
        }

        $stmtEventDetails->close();
        header("Location: view_event.php?event_id=$eventId");
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}
// Place this function before your form submission logic
function hasCanceledEvent($conn, $eventId, $UserID)
{
    $sqlCancelCheck = "SELECT * FROM cancel_reason WHERE event_id = ? AND UserID = ?";
    $stmtCancelCheck = $conn->prepare($sqlCancelCheck);
    $stmtCancelCheck->bind_param("ii", $eventId, $UserID);
    $stmtCancelCheck->execute();
    $resultCancelCheck = $stmtCancelCheck->get_result();

    $hasCanceled = $resultCancelCheck->num_rows > 0;

    $stmtCancelCheck->close();
    return $hasCanceled;
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

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="css/main.css">
</head>

<body>

    <?php

    require_once('../db.connection/connection.php');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check if UserID is set in the session
        if (isset($_SESSION['UserID'])) {
            $UserID = $_SESSION['UserID'];

            // Prepare and execute a query to fetch the specific admin's data
            $sql = "SELECT * FROM user WHERE UserID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $UserID); // Assuming UserID is an integer
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $LastName = $row['LastName'];
                    $FirstName = $row['FirstName'];
                    $MI = $row['MI'];
                    $Position = $row['Position']; // Corrected the column name
                    $Image = $row['Image'];


                    // Now, you have the specific admin's data
                }
            } else {
                echo "No records found";
            }

            $stmt->close();
        } else {
            // Redirect to login page or handle the case where UserID is not set in the session
            header("Location: login.php");
            exit();
        }
    }
    ?>
    <?php
    if (isset($_SESSION['success'])) {
        echo "<script>
        Swal.fire({
            title: 'Success!',
            text: '" . $_SESSION['success'] . "',
            icon: 'success',
            customClass: {
                popup: 'larger-swal'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'userDashboard.php'; 
            }
        });
    </script>";
        unset($_SESSION['success']);
    }

    if (isset($_SESSION['error'])) {
        echo "<script>
        Swal.fire({
            title: 'Error!',
            text: '" . $_SESSION['error'] . "',
            icon: 'error',
            customClass: {
                popup: 'larger-swal'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'userDashboard.php'; 
            }
        });
    </script>";
        unset($_SESSION['error']);
    }
    ?>

    <!--=========== SIDEBAR =============-->
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
                <a href="userDashboard.php  ">
                    <i class="bx bxs-grid-alt"></i>
                    <span class="nav-item">Dashboard</span>
                </a>
                <span class="tooltip">Dashboard</span>
            </li>

            <li class="events-side first nav-sidebar">
                <a href="#" class="a-events">
                    <i class='bx bx-archive'></i>
                    <span class="nav-item">Events</span>
                    <i class='bx bx-chevron-down hide'></i>
                </a>
                <span class="tooltip">Events</span>
                <div class="uno">
                    <ul>
                        <a href="landingPageU.php">Join Event</a>
                        <a href="history.php">History</a>
                        <a href="cancelEventU.php">Cancelled
                            <span><?php echo $totalCancelledEvents; ?></span></span></a>
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
    <style>
        @media (max-width: 768px) {
            .event-details .details {
                width: 100%;
                padding: 1rem;
            }
        }
    </style>
    <!-- ============ CONTENT ============-->
    <div class="main-content">
        <div class="containerr">
            <h3 class="dashboard">EVENT DETAILS</h3>
            <!-- view event starts-->
            <section class="event-details">

                <h1 class="heading">event details</h1>

                <div class="details">
                    <div class="event-info">
                        <h3><?php echo $_SESSION['event_data']['eventTitle']; ?></h3>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo $_SESSION['event_data']['eventLocation']; ?>
                        </p>
                    </div>

                    <?php if (!empty($_SESSION['event_data']['eventPhoto'])): ?>
                        <div class="info">
                            <img src="<?php echo $_SESSION['event_data']['eventPhoto']; ?>" alt="">
                        </div>
                    <?php endif; ?>

                    <div class="description">
                        <h3>event description</h3>
                        <p><?php echo $_SESSION['event_data']['eventDesc']; ?></p>
                        <ul>
                            <li>Date:
                                <?php echo $_SESSION['event_data']['eventDateStart'] . ' - ' . $_SESSION['event_data']['eventDateEnd']; ?>
                            </li>
                            <li>Time:
                                <?php echo $_SESSION['event_data']['eventTimeStart'] . ' - ' . $_SESSION['event_data']['eventTimeEnd']; ?>
                            </li>
                            <li>Event Type: <?php echo $_SESSION['event_data']['eventType']; ?></li>
                            <li>Event Mode: <?php echo $_SESSION['event_data']['eventMode']; ?></li>
                            <li>Audience: <?php echo $_SESSION['event_data']['audienceType']; ?></li>
                            <?php if ($_SESSION['event_data']['eventMode'] !== 'Face-to-Face'): ?>
                                <li>Event link: <a href="<?php echo $_SESSION['event_data']['eventLink']; ?>"
                                        target="_blank"><?php echo $_SESSION['event_data']['eventLink']; ?></a></li>
                            <?php endif; ?>
                            <?php if ($_SESSION['event_data']['eventMode'] === 'Hybrid' || $_SESSION['event_data']['eventMode'] === 'Face-to-Face'): ?>
                                <li>Location: <?php echo $_SESSION['event_data']['eventLocation']; ?></li>
                            <?php endif; ?>
                            <li>Status: <?php echo $_SESSION['event_data']['eventStatus']; ?></li>

                            <li>Participants: <?php echo $participantRatio; ?> </li>
                        </ul>
                        <!-- Sponsors and Speakers Section -->
                        <div class="sponsors-speakers">
                            <div class="sponsor-column">
                                <h3>Sponsors</h3>
                                <ul>
                                    <?php if (!empty($_SESSION['event_data']['sponsors'])): ?>
                                        <?php foreach ($_SESSION['event_data']['sponsors'] as $sponsor): ?>
                                            <li><?php echo $sponsor['sponsor_Name']; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>No sponsors available for this event.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div class="speaker-column">
                                <h3>Speakers</h3>
                                <ul>
                                    <?php if (!empty($_SESSION['event_data']['speakers'])): ?>
                                        <?php foreach ($_SESSION['event_data']['speakers'] as $speaker): ?>
                                            <li><?php echo $speaker['speaker_firstName'] . ' ' . $speaker['speaker_MI'] . ' ' . $speaker['speaker_lastName']; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>No speakers available for this event.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <style>
                        .sponsors-speakers {
                            display: flex;
                            gap: 40px;
                        }

                        .sponsor-column,
                        .speaker-column {
                            flex: 1;
                            text-align: left;
                        }

                        .sponsor-column ul,
                        .speaker-column ul {
                            list-style: none;
                            padding: 0;
                        }

                        .sponsor-column h3,
                        .speaker-column h3 {
                            margin-bottom: 10px;
                            font-weight: bold;
                            font-size: 1.2em;
                        }
                    </style>


                    <!-- <form action="" method="post" class="flex-btn">
                            <a href="applyEvent.php" class="btn">Join Event</a>
                        </form> -->
                    <form action="" method="post" class="flex-btn">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                        <button type="submit" class="btn">Join Event</button>
                    </form>
                    <div class="back-button-container">
                        <a href="./landingPageU.php" class="back-button">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

            </section>
            <!-- view event ends-->

        </div>


    </div>



    <!--CONFIRMATION BUTTON-->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function confirmSaveChanges(event) {

                event.preventDefault();

                Swal.fire({
                    title: 'Are you sure?',
                    text: "Do you want to join this event?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, join!',
                    cancelButtonText: 'No, cancel',
                    padding: '3rem',
                    customClass: {
                        popup: 'larger-swal'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        event.target.submit();
                    } else {
                        return false;
                    }
                });
            }
            document.querySelector('form').addEventListener('submit', confirmSaveChanges);
        });
    </script>


    <!--JS -->
    <script src="js/eventscript.js"></script>


    <!--sidebar functionality-->
    <script src="js/sidebar.js"></script>






    <script>

        let dropdown_items = document.querySelectorAll('.event-filter form .dropdown-container .dropdown .lists .items');

        dropdown_items.forEach(items => {
            items.onclick = () => {
                items_parent = items.parentElement.parentElement;
                let output = items_parent.querySelector('.output');
                output.value = items.innerText;
            }
        });

    </script>
</body>


</html>