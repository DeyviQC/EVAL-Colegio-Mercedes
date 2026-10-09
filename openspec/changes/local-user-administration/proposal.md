# Proposal: Local User Administration

Status: proposed for human approval, 2026-10-08. No application implementation is authorized by this document. The request to continue the real EVAL application establishes delivery intent; it does not select the previously deferred account policies.

Superseding approval: after the concrete rules and screen approval request, the human replied Continue and selected the collaborator's frontend, then clarified that its screens had been presented to and accepted by the professor. Subsequent Sigue authorizes implementation of this bounded account unit using those accepted screens as its visual baseline. Earlier proposed status is historical; no broader administrative policy or global planning gate is approved by inference.

## Outcome

The Director/Admin can create actual teacher and student accounts through EVAL, provide initial access, and manage their active status without editing SQL or invoking development seed scripts. Accounts persist in the existing local MySQL database. Creation alone grants no course access: enrollment and teacher assignment remain separate academic operations.

## Recommended bounded decisions

- Director/Admin alone manages accounts. Vice Principal, teacher and student receive no account-directory or maintenance permission.
- This delivery creates only teacher and student accounts, with exactly one role. Existing director and vice-principal accounts remain outside maintenance scope, preserving administrator access without introducing an unresolved last-admin policy.
- The director supplies a display name and a unique username. Usernames contain 3–64 lowercase ASCII letters, digits, dot, underscore or hyphen, starting with a letter; inputs are trimmed and normalized to lowercase before uniqueness checks. Existing provisioned usernames retain their authentication behavior.
- Generate an initial random 20-character password on the server, display it only after confirmed creation, and never expose a password hash. A teacher or student can change their own password using their current password. Accepted passwords are 12–72 UTF-8 bytes; store bcrypt hashes with cost 12, matching the existing development provisioning. These proposed defaults are not institutional policy until approved.
- A director can reset a teacher/student password to a newly generated password. Reset and self-service password change revoke previous sessions. Credentials must not appear in logs, URLs, directory responses, screenshots, or retained audit payloads.
- Director/Admin can deactivate or reactivate a teacher/student account. Deactivation denies subsequent authenticated requests but preserves identity, enrollment, assignment, authorship and historical records. It does not close or transfer academic relationships.
- No deletion, role changes, administrator creation, bulk import, email/SMS recovery, Internet service or automatic enrollment/assignment in this delivery.

## Acceptance

A director creates a teacher and student in the actual application; each signs in, changes their password and obtains only their own role navigation. Existing academic UI assigns/enrolls those accounts. A teacher publishes a material and the enrolled student consults it. A reset or deactivation stops previous sessions; historical evidence remains. Non-director maintenance attempts fail on the server.

## Remaining boundaries

The initial password is shown once; an uncertain response must never automatically replay account creation or reset. The director can explicitly reset an account after checking its directory entry. Mandatory first-login change, multi-role users, username rename, administrator succession and production institutional policies require separate decisions. This proposal does not close global architecture, deployment, backup, LAN acceptance or load-testing gates.
