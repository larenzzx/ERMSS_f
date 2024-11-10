<?php
session_start();
require_once('../db.connection/connection.php');

if (isset($_GET['event_id'])) {
    $eventId = intval($_GET['event_id']);

    $deleteAttendanceSql = "DELETE FROM attendance WHERE participant_id IN (SELECT participant_id FROM eventparticipants WHERE event_id = ?)";

    if ($stmt = $conn->prepare($deleteAttendanceSql)) {
        $stmt->bind_param("i", $eventId);
        if (!$stmt->execute()) {
            echo "<script>alert('Error deleting attendance records!'); window.location.href='../admin/cancelEvent.php';</script>";
            exit;
        }
        $stmt->close();
    }

    $deleteParticipantsSql = "DELETE FROM eventparticipants WHERE event_id = ?";

    if ($stmt = $conn->prepare($deleteParticipantsSql)) {
        $stmt->bind_param("i", $eventId);
        if (!$stmt->execute()) {
            echo "<script>alert('Error deleting participants!'); window.location.href='../admin/cancelEvent.php';</script>";
            exit;
        }
        $stmt->close();
    }

    // Delete sponsors
    $deletePendingSponsorsSql = "DELETE FROM sponsor WHERE event_id = ?";
    if ($stmt = $conn->prepare($deletePendingSponsorsSql)) {
        $stmt->bind_param("i", $eventId);
        if (!$stmt->execute()) {
            $_SESSION['error'] = 'Error deleting pending sponsors!';
            redirectBasedOnRole($conn);
            exit;
        }
        $stmt->close();
    }
    // Delete speakers
    $deletePendingSpeakersSql = "DELETE FROM speaker WHERE event_id = ?";
    if ($stmt = $conn->prepare($deletePendingSpeakersSql)) {
        $stmt->bind_param("i", $eventId);
        if (!$stmt->execute()) {
            $_SESSION['error'] = 'Error deleting pending speakers!';
            redirectBasedOnRole($conn);
            exit;
        }
        $stmt->close();
    }
    // Delete cancel_reason
    $deleteCancelReasonSql = "DELETE FROM cancel_reason WHERE event_id = ?";
    if ($stmt = $conn->prepare($deleteCancelReasonSql)) {
        $stmt->bind_param("i", $eventId);
        if (!$stmt->execute()) {
            $_SESSION['error'] = 'Error deleting pending sponsors!';
            redirectBasedOnRole($conn);
            exit;
        }
        $stmt->close();
    }
    $deleteEventSql = "DELETE FROM Events WHERE event_id = ?";

    if ($stmt = $conn->prepare($deleteEventSql)) {
        $stmt->bind_param("i", $eventId);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Deleted successfully!';
            header('Location: ../admin/cancelEvent.php');
            exit;
        } else {
            echo "<script>alert('Error deleting event!'); window.location.href='../admin/cancelEvent.php';</script>";
        }
        $stmt->close();
        $conn->close();
    } else {
        echo "<script>alert('Failed to prepare the SQL statement!'); window.location.href='../admin/cancelEvent.php';</script>";
    }
} else {
    echo "<script>alert('Invalid event ID!'); window.location.href='../admin/cancelEvent.php';</script>";
}

mysqli_close($conn);
?>