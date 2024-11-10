<?php
include('../function/F.allUser.php');

// Fetch distinct years from the Events table
$yearQuery = "SELECT DISTINCT YEAR(date_start) AS event_year FROM Events ORDER BY event_year DESC";
$yearResult = mysqli_query($conn, $yearQuery);
$years = mysqli_fetch_all($yearResult, MYSQLI_ASSOC);


// Fetch total events 
$totalEventsQuery = "SELECT COUNT(*) AS totalEvents FROM Events";
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
?>

<?php
// Fetch all users with the specified fields
$userQuery = $conn->query("
    SELECT 
        UserID, 
        LastName, 
        FirstName, 
        MI, 
        Gender, 
        Age, 
        Email, 
        Password, 
        ContactNo, 
        Address, 
        Affiliation, 
        Position, 
        Image, 
        EducationalAttainment, 
        Role, 
        'user' as userType 
    FROM user
");
$allUsers = [];
while ($user = $userQuery->fetch_assoc()) {
    $allUsers[] = $user;
}

// Fetch all admins with the specified fields
$adminQuery = $conn->query("
    SELECT 
        AdminID as UserID, 
        LastName, 
        FirstName, 
        MI, 
        Gender, 
        Email, 
        Password, 
        ContactNo, 
        Address, 
        Affiliation, 
        Position, 
        Image, 
        Role, 
        'admin' as userType 
    FROM admin
");
$allAdmins = [];
while ($admin = $adminQuery->fetch_assoc()) {
    $allAdmins[] = $admin;
}

// Merge both users and admins into a single array
$allUsersAndAdmins = array_merge($allUsers, $allAdmins);
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
                    <div class="select-container" style="position: relative; display: inline-block;">
                        <select id="userDropdown" onchange="filterByUserType()" style="width: 200px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; 
                            background-color: #f9f9f9; color: #333; font-size: 16px; cursor: pointer; 
                            transition: border-color 0.3s ease; -webkit-appearance: none; 
                            -moz-appearance: none; appearance: none;">
                            <option value="all" selected>All Users</option>
                            <option value="user">User</option>
                            <option value="admin">Admins</option>
                        </select>
                    </div>


                    <div>
                        <input class="tb_input" placeholder="Search..." oninput="filterTable()">
                        <button class="add_new"><?php echo count($allUsersAndAdmins); ?> Users</button>
                    </div>
                </div>
                <div class="table_section">
                    <table id="userTable" class="tb_eco">
                        <thead class="tb_head">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Affiliation</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsersAndAdmins as $user): ?>
                                <?php if ($user['Role'] !== 'superadmin'): ?>
                                    <tr data-user-type="<?php echo $user['userType']; ?>">
                                        <td><?php echo $user['UserID']; ?></td>
                                        <td><?php echo $user['FirstName'] . ' ' . $user['LastName']; ?></td>
                                        <td><?php echo $user['Email']; ?></td>
                                        <td><?php echo $user['Affiliation']; ?></td>
                                        <td><?php echo $user['Role']; ?></td>
                                        <td>
                                            <button class="action-button" data-userid="<?php echo $user['UserID']; ?>"
                                                data-usertype="<?php echo $user['userType']; ?>"
                                                data-fullname="<?php echo $user['FirstName'] . ' ' . $user['LastName']; ?>"
                                                data-email="<?php echo $user['Email']; ?>"
                                                data-image="<?php echo $user['Image']; ?>"
                                                data-gender="<?php echo $user['Gender']; ?>"
                                                data-age="<?php echo $user['userType'] === 'admin' ? 'N/A' : $user['Age']; ?>"
                                                data-affiliation="<?php echo $user['Affiliation']; ?>"
                                                data-educationalattainment="<?php echo $user['userType'] === 'admin' ? 'N/A' : $user['EducationalAttainment']; ?>"
                                                data-contact="<?php echo $user['ContactNo']; ?>"
                                                data-position="<?php echo $user['Position']; ?>"
                                                data-role="<?php echo $user['Role']; ?>"
                                                onclick="showUserProfile(<?php echo $user['UserID']; ?>, '<?php echo $user['userType']; ?>')">View
                                                Profile</button>

                                            <?php if ($_SESSION['Role'] === 'superadmin'): ?>
                                                <button class="btn_delete"
                                                    onclick="confirmDeleteEvent('<?php echo $user['UserID']; ?>', '<?php echo $user['userType']; ?>')">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>



    <script>
        function showUserProfile(userId, userType) {
            const button = document.querySelector(`button[data-userid="${userId}"][data-usertype="${userType}"]`);

            const fullName = button.dataset.fullname;
            const email = button.dataset.email;
            const affiliation = button.dataset.affiliation;
            const gender = button.dataset.gender;
            const age = button.dataset.age;
            const educationalAttainment = button.dataset.educationalattainment;
            const contact = button.dataset.contact;
            const position = button.dataset.position;
            const role = button.dataset.role;
            const image = button.dataset.image;

            Swal.fire({
                title: 'User Profile',
                html: `
            <div style="text-align: left; padding:2rem;">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <img src="${image ? '../assets/img/profilePhoto/' + image : '../assets/img/profile.jpg'}" 
                         alt="Profile Image" 
                         style="width: 100px; height: 100px; border-radius: 50%;">
                </div>

                <strong><h3 style="margin-bottom: 0.25rem; margin-top: 4rem">Personal Info:</h3></strong>
                <strong>Name:</strong> ${fullName} <br/>
                ${userType !== 'admin' ? `<strong>Age:</strong> ${age} <br/>` : ''}
                <strong>Gender:</strong> ${gender} <br/>
                <strong>Educational Attainment:</strong> ${educationalAttainment} <br/><br/>

                <strong><h3 style="margin-bottom: 0.25rem;">Contact Info:</h3></strong>
                <strong>Email:</strong> ${email} <br/>
                <strong>Contact:</strong> ${contact} <br/><br/>

                <strong><h3 style="margin-bottom: 0.25rem;">Profile Details:</h3></strong>
                <strong>Affiliation:</strong> ${affiliation} <br/>
                <strong>Occupation:</strong> ${position} <br/><br/>
                <strong>Role:</strong> ${role} <br/><br/>

                <button id="editProfileButton" style="background-color: #28a745; color: white; border: none; padding: 0.5rem 1rem; cursor: pointer;">Edit Profile</button>
            </div>
        `,
                showCancelButton: true,
                cancelButtonText: 'Close',
                customClass: {
                    popup: 'larger-swal'
                },
            });

            document.getElementById('editProfileButton').addEventListener('click', function () {
                Swal.fire({
                    title: 'Edit User Profile',
                    html: `
                    <div style="text-align: left; padding: 2.5rem; font-size:2rem;">
                        <label>Name:</label>
                        <input type="text" id="editName" value="${fullName}" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                        <label>Email:</label>
                        <input type="email" id="editEmail" value="${email}" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                        <label>Affiliation:</label>
                        <input type="text" id="editAffiliation" value="${affiliation}" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                        <label>Gender:</label>
                        <select id="editGender" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                            <option value="Male" ${gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${gender === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Other" ${gender === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                        ${role !== 'Admin' ? `
                        <label>Age:</label>
                        <input type="number" id="editAge" value="${age}" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">` : ''}
                        <label>Contact:</label>
                        <input type="text" id="editContact" value="${contact}" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                        <label>Position:</label>
                        <input type="text" id="editPosition" value="${position}" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                        <label>Profile Photo:</label>
                        <input type="file" id="editProfilePhoto" style="width: 100%; margin-bottom: 1rem; font-size:1.6rem;">
                    </div>
                `,
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'larger-swal'
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        const updatedUser = {
                            userId: userId,
                            name: document.getElementById('editName').value,
                            email: document.getElementById('editEmail').value,
                            affiliation: document.getElementById('editAffiliation').value,
                            gender: document.getElementById('editGender').value,
                            contact: document.getElementById('editContact').value,
                            position: document.getElementById('editPosition').value,
                            image: document.getElementById('editProfilePhoto').files[0]
                        };

                        if (role !== 'Admin') {
                            updatedUser.age = document.getElementById('editAge').value;
                        }

                        const formData = new FormData();
                        for (const key in updatedUser) {
                            formData.append(key, updatedUser[key]);
                        }

                        const endpoint = role === 'Admin' ? 'updateAdmin.php' : 'updateUser.php';

                        fetch(endpoint, {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: 'User profile updated.',
                                        icon: 'success',
                                        customClass: {
                                            popup: 'larger-swal'
                                        },
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'An error occurred while updating the profile.',
                                        icon: 'error',
                                        customClass: {
                                            popup: 'larger-swal'
                                        },
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error updating profile:', error);
                            });
                    }
                });
            });
        }
    </script>





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
        });
    </script>";
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['duplicate'])) {
        echo "<script>
        Swal.fire({
          title: 'Error!',
          text: '" . $_SESSION['duplicate'] . "',
          icon: 'error',
          customClass: {
          popup: 'larger-swal' 
        }  
        });
    </script>";
        unset($_SESSION['duplicate']);
    }
    ?>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status === 'success') {
            Swal.fire({
                title: "Success!",
                text: "Event successfully updated!",
                icon: "success",
                customClass: {
                    popup: 'larger-swal'
                }
            }).then(() => {
                const newUrl = window.location.pathname;
                window.history.replaceState(null, '', newUrl);
            });
        }

        if (status === 'cancelled') {
            Swal.fire({
                title: "Cancelled!",
                text: "Event successfully cancelled!",
                icon: "error",
                customClass: {
                    popup: 'larger-swal'
                }
            }).then(() => {
                const newUrl = window.location.pathname;
                window.history.replaceState(null, '', newUrl);
            });
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const actionButtons = document.querySelectorAll('.action-button');

        actionButtons.forEach(button => {
            button.addEventListener('click', function () {
                const userId = this.dataset.userid; // Get the user ID from the button's data attribute
                const eventId = <?php echo $eventId; ?>; // Get the event ID from PHP variable

                // Send the user ID and event ID to the server
                fetch('addParticipant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        userId: userId,
                        eventId: eventId,
                    }),
                })
                    .then(response => {
                        if (response.ok) {
                            // Reload the page after successful addition of participant
                            window.location.reload();
                        } else {
                            console.error('Failed to add participant');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            });
        });
    });
</script>


<script>
    function confirmDeleteEvent(userId) {
        Swal.fire({
            title: 'Delete User?',
            text: 'Are you sure you want to delete this user?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'No, cancel!',
            padding: '3rem',
            customClass: {
                popup: 'larger-swal'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `deleteUser.php?user_id=${userId}`;
            }
        });
    }
</script>


<script>
    function filterByUserType() {
        const userType = document.getElementById("userDropdown").value;
        const rows = document.querySelectorAll("#userTable tbody tr");

        rows.forEach(row => {
            if (userType === "all" || row.getAttribute("data-user-type") === userType) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

</script>


</html>