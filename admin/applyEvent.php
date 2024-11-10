<?php
include('../function/F.addParticipant.php');

$eventId = isset($_GET['event_id']) ? $_GET['event_id'] : null;

$alertMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once('../db.connection/connection.php');

    if (isset($_SESSION['AdminID'])) {
        $UserID = $_SESSION['AdminID'];
        $eventId = isset($_POST['event_id']) ? $_POST['event_id'] : null;
        $participantId = isset($_POST['user_id']) ? $_POST['user_id'] : null;

        if ($eventId && $participantId) {

            // Check if event is ongoing
            function isEventOngoing($conn, $eventId)
            {
                $sqlOngoing = "SELECT COUNT(*) AS ongoing_count FROM Events WHERE event_id = ? AND NOW() BETWEEN CONCAT(date_start, ' ', time_start) AND CONCAT(date_end, ' ', time_end)";
                $stmtOngoing = $conn->prepare($sqlOngoing);
                $stmtOngoing->bind_param("i", $eventId);
                $stmtOngoing->execute();
                $resultOngoing = $stmtOngoing->get_result();
                $ongoingData = $resultOngoing->fetch_assoc();
                $stmtOngoing->close();
                return $ongoingData['ongoing_count'] > 0;
            }

            // Function to get the current participant count for an event
            function getParticipantCount($conn, $eventId)
            {
                $sqlCount = "SELECT COUNT(*) AS participant_count FROM EventParticipants WHERE event_id = ?";
                $stmtCount = $conn->prepare($sqlCount);
                $stmtCount->bind_param("i", $eventId);
                $stmtCount->execute();
                $resultCount = $stmtCount->get_result();
                $countData = $resultCount->fetch_assoc();
                $stmtCount->close();
                return $countData['participant_count'];
            }

            // Function to get the participant limit for an event
            function getParticipantLimit($conn, $eventId)
            {
                $sqlLimit = "SELECT participant_limit FROM Events WHERE event_id = ?";
                $stmtLimit = $conn->prepare($sqlLimit);
                $stmtLimit->bind_param("i", $eventId);
                $stmtLimit->execute();
                $resultLimit = $stmtLimit->get_result();
                $limitData = $resultLimit->fetch_assoc();
                $stmtLimit->close();
                return $limitData['participant_limit'];
            }

            // Function to add participant to EventParticipants
            function addParticipant($conn, $eventId, $participantId)
            {
                $sqlInsert = "INSERT INTO EventParticipants (event_id, UserID) VALUES (?, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("ii", $eventId, $participantId);
                return $stmtInsert->execute();
            }

            if (isEventOngoing($conn, $eventId)) {
                // Event is ongoing, display error
                $alertMessage = "
                    <script>
                        Swal.fire({
                            title: 'Event Ongoing!',
                            text: 'Participants cannot be added to an ongoing event.',
                            icon: 'error',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = '';
                            }
                        });
                    </script>
                ";
            } else {
                $currentCount = getParticipantCount($conn, $eventId);
                $participantLimit = getParticipantLimit($conn, $eventId);

                if ($currentCount >= $participantLimit) {
                    // Participant limit reached
                    $alertMessage = "
                        <script>
                            Swal.fire({
                                title: 'Limit Reached!',
                                text: 'The participant limit for this event has been reached.',
                                icon: 'warning',
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = '';
                                }
                            });
                        </script>
                    ";
                } else {
                    // Check if participant has canceled before
                    $checkCancelQuery = "SELECT COUNT(*) AS cancel_count FROM cancel_reason WHERE event_id = ? AND UserID = ?";
                    $stmtCheckCancel = $conn->prepare($checkCancelQuery);
                    $stmtCheckCancel->bind_param("ii", $eventId, $participantId);
                    $stmtCheckCancel->execute();
                    $resultCheckCancel = $stmtCheckCancel->get_result();
                    $cancelData = $resultCheckCancel->fetch_assoc();
                    $stmtCheckCancel->close();

                    if ($cancelData['cancel_count'] > 0) {
                        // Participant has canceled before, ask to rejoin
                        $alertMessage = "
                            <script>
                                Swal.fire({
                                    title: 'Notice!',
                                    text: 'This participant already cancelled. Rejoin?',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonText: 'Yes, rejoin!',
                                    cancelButtonText: 'No, cancel'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        document.getElementById('rejoinForm').submit();
                                    } else {
                                        Swal.fire('Cancelled', 'Action cancelled.', 'error');
                                    }
                                });
                            </script>
                        ";

                        echo '
                        <form id="rejoinForm" action="" method="post" style="display:none;">
                            <input type="hidden" name="event_id" value="' . $eventId . '">
                            <input type="hidden" name="user_id" value="' . $participantId . '">
                            <input type="hidden" name="rejoin_action" value="1">
                        </form>';
                    } else {
                        // Check if participant is already enrolled
                        $checkParticipantQuery = "SELECT COUNT(*) AS participant_count FROM EventParticipants WHERE event_id = ? AND UserID = ?";
                        $stmtCheckParticipant = $conn->prepare($checkParticipantQuery);
                        $stmtCheckParticipant->bind_param("ii", $eventId, $participantId);
                        $stmtCheckParticipant->execute();
                        $resultCheckParticipant = $stmtCheckParticipant->get_result();
                        $participantData = $resultCheckParticipant->fetch_assoc();
                        $stmtCheckParticipant->close();

                        if ($participantData['participant_count'] > 0) {
                            // Participant already added
                            $alertMessage = "
                                <script>
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'The participant is already added to the event.',
                                        icon: 'error'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = '';
                                        }
                                    });
                                </script>
                            ";
                        } else {
                            // Add new participant
                            if (addParticipant($conn, $eventId, $participantId)) {
                                $alertMessage = "
                                    <script>
                                        Swal.fire({
                                            title: 'Success!',
                                            text: 'Participant added successfully!',
                                            icon: 'success'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href = '';
                                            }
                                        });
                                    </script>
                                ";
                            }
                        }
                    }

                    // If rejoin is confirmed
                    if (isset($_POST['rejoin_action']) && $_POST['rejoin_action'] == 1) {
                        if (addParticipant($conn, $eventId, $participantId)) {
                            // Delete from cancel_reason
                            $deleteCancelQuery = "DELETE FROM cancel_reason WHERE event_id = ? AND UserID = ?";
                            $stmtDeleteCancel = $conn->prepare($deleteCancelQuery);
                            $stmtDeleteCancel->bind_param("ii", $eventId, $participantId);
                            $stmtDeleteCancel->execute();
                            $stmtDeleteCancel->close();

                            $alertMessage = "
                                <script>
                                    Swal.fire({
                                        title: 'Success!',
                                        text: 'Rejoined the event successfully!',
                                        icon: 'success'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = '';
                                        }
                                    });
                                </script>
                            ";
                        }
                    }
                }
            }
        }
    } else {
        header("Location: ../login.php");
        exit();
    }
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

    <!--browser icon-->
    <link rel="icon" href="img/wesmaarrdec.jpg" type="image/png">

    <link rel="stylesheet" href="css/main.css">


</head>

<body>


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
                        <!-- <a href="accountSettings.php">Account Settings</a> -->
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
            <div class="tables container">
                <div class="table_header">
                    <p>All Users</p>
                    <div>
                        <input class="tb_input" placeholder="Search..." oninput="filterTable()">
                        <button class="add_new"> <?php echo $totalUsersCount; ?> Users</button>
                    </div>
                </div>
                <div class="table_section">
                    <div class="table_section">
                        <table id="userTable" class="tb_eco">
                            <thead class="tb_head">
                                <tr>
                                    <th>Name</th>
                                    <th style="width: 7%">Age</th>
                                    <th style="width: 10%">Gender</th>
                                    <th>Email</th>
                                    <th>Occupation</th>
                                    <th>Affiliation</th>
                                    <th style="width: 9%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td><?php echo $user['FirstName'] . ' ' . $user['LastName']; ?></td>
                                        <td><?php echo $user['Age']; ?></td>
                                        <td><?php echo $user['Gender']; ?></td>
                                        <td><?php echo $user['Email']; ?></td>
                                        <td><?php echo $user['Position']; ?></td>
                                        <td><?php echo $user['Affiliation']; ?></td>
                                        <td>
                                            <form class="confirmationForm" action="" method="post">
                                                <button type="button" class="confirmBtn action-button">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <script>
                        // Select all confirm buttons
                        const confirmButtons = document.querySelectorAll('.confirmBtn');

                        confirmButtons.forEach(button => {
                            button.addEventListener('click', function (e) {
                                e.preventDefault(); // Prevent default button action

                                const form = this.closest('.confirmationForm'); // Get the associated form

                                Swal.fire({
                                    title: 'Are you sure?',
                                    text: "Do you want to proceed with this action?",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Yes, proceed!',
                                    cancelButtonText: 'No, cancel',
                                    customClass: {
                                        popup: 'larger-swal'
                                    },
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        form.submit(); // Submit the form
                                    } else {
                                        Swal.fire('Cancelled', 'Your action has been canceled.', 'error');
                                    }
                                });
                            });
                        });
                    </script>

                </div>
            </div>
        </div>
        <tbody>

    </div>


    <!--FILTER TABLE ROW-->
    <script>
        function filterTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.querySelector('.tb_input');
            filter = input.value.toUpperCase();
            console.log("Filter text:", filter);

            table = document.getElementById("userTable");
            tr = table.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                td = tr[i].getElementsByTagName("td")[1];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    console.log("Text content:", txtValue);
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

    </script>








    <!--JS -->
    <script src="js/eventscript.js"></script>


    <!--sidebar functionality-->
    <script src="js/sidebar.js"></script>

    <!--chart js-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script src="js/myChart.js"></script>

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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php echo $alertMessage; ?>


</html>