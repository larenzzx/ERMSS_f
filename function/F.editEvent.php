<?php
require_once('../db.connection/connection.php');

function showAlert($message, $redirectPath = null)
{
    echo "<script>alert('$message');";
    if ($redirectPath) {
        echo "window.location.href = '$redirectPath';";
    }
    echo "</script>";
}

function cleanInput($input)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}
if (isset($_GET['event_id'])) {
    $eventId = cleanInput($_GET['event_id']);

    // Fetch event details
    $sql = "SELECT * FROM Events WHERE event_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $eventId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $eventDetails = $result->fetch_assoc();

        $eventTitle = $eventDetails['event_title'];
        $eventDescription = $eventDetails['event_description'];
        $eventType = $eventDetails['event_type'];
        $eventMode = $eventDetails['event_mode'];
        $audienceType = $eventDetails['audience_type'];
        $eventLink = ($eventMode === 'Face-to-Face') ? '' : $eventDetails['event_link'];
        $eventLocation = ($eventMode === 'Online') ? '' : $eventDetails['location'];
        $eventDateStart = $eventDetails['date_start'];
        $eventDateEnd = $eventDetails['date_end'];
        $eventTimeStart = $eventDetails['time_start'];
        $eventTimeEnd = $eventDetails['time_end'];
        $eventPhotoPath = $eventDetails['event_photo_path'];
        $participantLimit = $eventDetails['participant_limit'];

        // Get current date and time in the event's timezone
        $eventTimeZone = new DateTimeZone('Asia/Manila');
        $currentDateTime = new DateTime('now', $eventTimeZone);
        $eventStartDateTime = new DateTime($eventDetails['date_start'] . ' ' . $eventDetails['time_start'], $eventTimeZone);
        $eventEndDateTime = new DateTime($eventDetails['date_end'] . ' ' . $eventDetails['time_end'], $eventTimeZone);

        // Determine the event status as either 'ongoing' or 'upcoming'
        if ($currentDateTime >= $eventStartDateTime && $currentDateTime <= $eventEndDateTime) {
            $eventStatus = 'ongoing';
        } elseif ($currentDateTime < $eventStartDateTime) {
            $eventStatus = 'upcoming';
        }


        $result->close();

        // Fetch speakers for the event
        $sqlSpeakers = "SELECT speaker_firstName, speaker_MI, speaker_lastName FROM speaker WHERE event_id = ?";
        $stmtSpeakers = $conn->prepare($sqlSpeakers);
        $stmtSpeakers->bind_param("i", $eventId);
        $stmtSpeakers->execute();
        $resultSpeakers = $stmtSpeakers->get_result();

        $speakers = [];
        while ($row = $resultSpeakers->fetch_assoc()) {
            $speakers[] = $row;
        }
        $stmtSpeakers->close();

        // Fetch sponsors for the event
        $sqlSponsors = "SELECT sponsor_Name FROM sponsor WHERE event_id = ?";
        $stmtSponsors = $conn->prepare($sqlSponsors);
        $stmtSponsors->bind_param("i", $eventId);
        $stmtSponsors->execute();
        $resultSponsors = $stmtSponsors->get_result();

        $sponsors = [];
        while ($row = $resultSponsors->fetch_assoc()) {
            $sponsors[] = $row;
        }
        $stmtSponsors->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Clean form data
            $eventTitle = cleanInput($_POST['event_title']);
            $eventDescription = cleanInput($_POST['event_description']);
            $eventType = cleanInput($_POST['event_type']);
            $audienceType = cleanInput($_POST['audience_type']);
            $eventMode = cleanInput($_POST['event_mode']);
            $eventLocation = cleanInput($_POST['location']);
            $eventDateStart = cleanInput($_POST['date_start']);
            $eventDateEnd = cleanInput($_POST['date_end']);
            $eventTimeStart = cleanInput($_POST['time_start']);
            $eventTimeEnd = cleanInput($_POST['time_end']);
            $participantLimit = cleanInput($_POST['participant_limit']);
            $eventPhotoPath = $eventDetails['event_photo_path'];

            // Handle file upload
            $uploadDir = "../admin/img/eventPhoto/";
            if (!empty($_FILES['event_photo']['name'])) {
                $newEventPhotoPath = $uploadDir . basename($_FILES['event_photo']['name']);
                if (move_uploaded_file($_FILES['event_photo']['tmp_name'], $newEventPhotoPath)) {
                    $eventPhotoPath = $newEventPhotoPath;
                }
            }

            // Update event link based on mode
            $eventLink = ($eventMode === 'Hybrid' || $eventMode === 'Online') ? cleanInput($_POST['zoom_link']) : '';

            if ($eventMode === 'Online') {
                $eventLocation = '';  // Clear location if Online
            }

            if ($eventMode === 'Face-to-Face') {
                $eventLink = '';  // Clear link if Face-to-Face
            }

            // Handle event cancellation
            $cancelReason = cleanInput($_POST['event_cancel_reason']);
            $cancelStatus = !empty($cancelReason) ? 'Cancelled' : '';

            // Check for duplicate event title
            $duplicateCheckSql = "SELECT COUNT(*) as count FROM Events WHERE event_title = ? AND event_id != ?";
            $duplicateCheckStmt = $conn->prepare($duplicateCheckSql);
            $duplicateCheckStmt->bind_param("si", $eventTitle, $eventId);
            $duplicateCheckStmt->execute();
            $duplicateResult = $duplicateCheckStmt->get_result();
            $duplicateRow = $duplicateResult->fetch_assoc();

            if ($duplicateRow['count'] > 0) {
                $_SESSION['duplicate'] = 'An event with this title already exists.';
                header("Location: ../admin/landingPage.php?event_id=$eventId&status=duplicate");
            } else {
                // Update the event details (same as before)
                $updateSql = "UPDATE Events SET 
                    event_title = ?, 
                    event_description = ?, 
                    event_type = ?, 
                    audience_type = ?, 
                    event_mode = ?, 
                    event_link = ?, 
                    location = ?, 
                    date_start = ?, 
                    date_end = ?, 
                    time_start = ?, 
                    time_end = ?, 
                    event_photo_path = ?, 
                    cancelReason = ?, 
                    event_cancel = ?, 
                    participant_limit = ? 
                    WHERE event_id = ?";

                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param(
                    "ssssssssssssssii",
                    $eventTitle,
                    $eventDescription,
                    $eventType,
                    $audienceType,
                    $eventMode,
                    $eventLink,
                    $eventLocation,
                    $eventDateStart,
                    $eventDateEnd,
                    $eventTimeStart,
                    $eventTimeEnd,
                    $eventPhotoPath,
                    $cancelReason,
                    $cancelStatus,
                    $participantLimit,
                    $eventId
                );

                if ($updateStmt->execute()) {
                    // First, delete existing sponsors for this event
                    $deleteSponsorSql = "DELETE FROM sponsor WHERE event_id = ?";
                    $stmtDeleteSponsor = $conn->prepare($deleteSponsorSql);
                    $stmtDeleteSponsor->bind_param("i", $eventId);
                    $stmtDeleteSponsor->execute();
                    $stmtDeleteSponsor->close();

                    $deleteSpeakerSql = "DELETE FROM speaker WHERE event_id = ?";
                    $stmtDeleteSpeaker = $conn->prepare($deleteSpeakerSql);
                    $stmtDeleteSpeaker->bind_param("i", $eventId);
                    $stmtDeleteSpeaker->execute();
                    $stmtDeleteSpeaker->close();

                    // Handle speaker updates/inserts
                    for ($i = 1; $i <= 5; $i++) {
                        $speakerFirstName = cleanInput($_POST["speaker{$i}_firstName"]);
                        $speakerMI = cleanInput($_POST["speaker{$i}_MI"]);
                        $speakerLastName = cleanInput($_POST["speaker{$i}_lastName"]);

                        if (!empty($speakerFirstName) || !empty($speakerMI) || !empty($speakerLastName)) {
                            $insertSpeakerSql = "INSERT INTO speaker (event_id, speaker_firstName, speaker_MI, speaker_lastName) 
                                         VALUES (?, ?, ?, ?)";
                            $stmtInsertSpeaker = $conn->prepare($insertSpeakerSql);
                            $stmtInsertSpeaker->bind_param("isss", $eventId, $speakerFirstName, $speakerMI, $speakerLastName);
                            $stmtInsertSpeaker->execute();
                            $stmtInsertSpeaker->close();
                        }
                    }

                    // Handle sponsors (similar to previous script)
                    for ($i = 1; $i <= 5; $i++) {
                        $sponsorName = cleanInput($_POST["sponsor{$i}_Name"]);

                        if (!empty($sponsorName)) {
                            $insertSponsorSql = "INSERT INTO sponsor (event_id, sponsor_Name) 
                                         VALUES (?, ?)";
                            $stmtInsertSponsor = $conn->prepare($insertSponsorSql);
                            $stmtInsertSponsor->bind_param("is", $eventId, $sponsorName);
                            $stmtInsertSponsor->execute();
                            $stmtInsertSponsor->close();
                        }
                    }

                    // Redirect based on success (same as before)
                    if (!empty($cancelReason)) {
                        if (!empty($cancelReason)) {
                            // Fetch all participants for the event
                            $fetchParticipantsSql = "SELECT UserID FROM eventparticipants WHERE event_id = ?";
                            $stmtFetchParticipants = $conn->prepare($fetchParticipantsSql);
                            $stmtFetchParticipants->bind_param("i", $eventId);

                            if ($stmtFetchParticipants->execute()) {
                                $resultParticipants = $stmtFetchParticipants->get_result();

                                // Insert cancellation reason for each participant
                                $insertCancelReasonSql = "INSERT INTO cancel_reason (event_id, UserID, description) VALUES (?, ?, ?)";
                                $stmtInsertCancelReason = $conn->prepare($insertCancelReasonSql);

                                while ($rowParticipant = $resultParticipants->fetch_assoc()) {
                                    $userId = $rowParticipant['UserID'];
                                    $stmtInsertCancelReason->bind_param("iis", $eventId, $userId, $cancelReason);
                                    $stmtInsertCancelReason->execute();
                                }

                                $stmtInsertCancelReason->close();
                            }

                            $stmtFetchParticipants->close();
                        }

                        $_SESSION['cancelled'] = 'Event successfully cancelled!';
                        header("Location: ../admin/landingPage.php?event_id=$eventId&status=cancelled");
                    } else {
                        $_SESSION['success'] = 'Event successfully updated!';
                        header("Location: ../admin/landingPage.php?event_id=$eventId&status=success");
                    }
                    exit();
                } else {
                    echo "Error updating record: " . $updateStmt->error;
                }

                $updateStmt->close();
            }

            $duplicateCheckStmt->close();
        }
    } else {
        die("Error: " . $stmt->error);
    }
} else {
    die("Event ID not provided.");
}


?>