# UI Review: User Administration

Status: proposed screen/interaction prototype for approval. This document is not a substitute runtime application and contains no application code.

Superseding status: the human accepted the bounded screen review and explicitly selected the collaborator's professor-approved screens as the visual baseline. Users and Mi cuenta now operate in the real application; this review remains the record of intended interactions.

## Director/Admin: Users

Navigation includes Users alongside existing academic sections. The screen shows a Create account action and a paginated list with Name, Username, Role, Status and Actions. Teacher and student accounts are listed; historical inactive accounts remain visible.

Create account opens a form with Name, Username and Role (Teacher / Student), followed by Create and Cancel. Explain that enrollment or assignment is performed separately. On confirmed success, show the account's username and generated initial password, with a warning to save it before dismissing the result. Never show that password in the account list.

An account's actions offer Reset password and Deactivate (or Reactivate). Reset confirmation identifies the account and explains that existing sessions will stop; confirmed success shows the newly generated password once. Deactivation confirmation explains that historical records remain and academic relationships are not closed automatically.

Pending actions disable repeat submission. A network-uncertain outcome shows Check account status and does not retry the mutation automatically. A directory-refresh failure after confirmed success says the operation succeeded but the list could not be updated.

## Every authenticated role: Change password

The session area includes Change password. The form has Current password, New password and Confirm new password with byte-limit validation. On confirmed change, clear all password fields and require sign-in with the new password. Report uncertainty without automatic retry; allow the user to sign in explicitly to establish the actual credential state.

## Accessibility and verification

Use the existing EVAL styles, labeled inputs, keyboard-accessible confirmation controls and live status/error feedback. At the existing tablet verification width, forms remain within the viewport and account actions remain usable. Server authorization applies regardless of rendered navigation.
