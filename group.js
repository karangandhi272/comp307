// Group page: loads the groups, shows notes and members, 
// and handles note uploads/edits as well as other member actions.
const groupId = new URLSearchParams(location.search).get("id");
let currentNote = null;

// Sends a POST request to an API file with the user's ticket and returns the parsed JSON.
function api(file, params) {
  params.ticket = localStorage.getItem("ticket");
  return fetch("api/" + file, { method: "POST", body: new URLSearchParams(params) })
    .then(response => response.json());
}

// Loads the group's info from the server and fills the page. It sends the user to 
// home if they can't access it. 
function loadGroup() {
  if (!groupId) { location.href = "home.html"; return; }

  api("groupinfo.php", { group_id: groupId })
    .then(output => {
      if (output === 0) { location.href = "home.html"; return; }
      showHeader(output);
      showNotes(output.notes, output.me);
      showMembers(output.members, output.me);
    }).catch(err => { console.log(err); alert("Could not reach the server") });
}

// Load the group as soon as the page is ready.
window.addEventListener("load", loadGroup)

// Clear the file picker whenever the upload popup closes after a Cancel, Escape, x or after uploading. 
window.addEventListener("load", () => {
  document.getElementById("upload").addEventListener("close", clearFile);
})

// Fills the header with the group name, invite code, member count and leader.
function showHeader(output) {
  document.title = output.group.name;
  document.getElementById("groupName").textContent = output.group.name;
  document.getElementById("inviteCode").textContent = output.group.invite_code;

  const leader = output.members.find(m => m.role === 'leader');
  document.getElementById("groupMeta").textContent = output.members.length + " members · led by " + (leader ? leader.display_name : "nobody");
}

// Constructs the shared notes list. The delete button only appears for the note's author 
// and group leader(s).
function showNotes(notes, me) {
  const table = document.getElementById("notesList");

  if (notes.length === 0) {
    table.innerHTML = `<tr><td><small>No notes yet. Upload a .txt file to get started.</small></td></tr>`;
    return;
  }

  let html = "";
  for (let i = 0; i < notes.length; i++) {
    const note = notes[i];

    // Only the author or the leader can view the delete button. 
    const canDelete = note.author_id === me.user_id || me.role === "leader";
    const deleteBtn = canDelete ? `<button onclick="event.stopPropagation(); deleteNote(${note.id})" aria-label="Delete note">x</button>`
      : "";

    html += `
        <tr onclick="showNote(${note.id})" style="cursor: pointer">
          <td>
            ${escapeHtml(note.title)}<br>
            <small>by ${escapeHtml(note.author ?? "unknown")}</small>
          </td>
          <td>${deleteBtn}</td>
        </tr>`;
  }
  table.innerHTML = html;
}

// Clears the preview back to its empty state in the case that a note is deleted or can't be loaded. 
function resetPreview() {
  document.getElementById("previewTitle").textContent = "Note preview";
  document.getElementById("notePreview").textContent = "Click a note to read it.";
  currentNote = null;
  cancelEdit();
}

// Loads one note and displays it in the preview panel.
function showNote(noteId) {
  api("getnote.php", { note_id: noteId })
    .then(output => {
      if (output === 0) {
        alert("Couldn't open that note. It may have been deleted.");
        resetPreview();
        loadGroup();
        return;
      }
      document.getElementById("previewTitle").textContent = output.title;
      document.getElementById("notePreview").textContent = output.content;
      currentNote = { id: noteId, title: output.title, content: output.content, version: output.version };
      document.getElementById("editBtn").hidden = false;
      cancelEdit();
    }).catch(err => { console.log(err); alert("Could not reach the server") });
}

// Deletes a note after the user confirms, then resets the preview panel and
// refreshes the list.
function deleteNote(noteId) {
  if (!confirm("Delete this note? This can't be undone.")) return;

  api("deltxt.php", { note_id: noteId })
    .then(output => {
      if (output === 0) { alert("Couldn't delete that note"); return; }

      resetPreview();
      loadGroup();
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Constructs the members list. Leaders can see Remove and Promote buttons next to
// non-leaders.
function showMembers(members, me) {
  document.getElementById("membersTitle").textContent = "Members (" + members.length + ")";

  let html = "";
  for (let i = 0; i < members.length; i++) {
    const member = members[i];
    let action = "";

    if (member.role === "leader") {
      action = `<small>Leader</small>`;
    } else if (me.role === "leader") {
      action = `<button onClick="removeMember(${member.user_id})">Remove</button>
          <button onClick="promoteMember(${member.user_id})">Promote</button>`;
    }

    const you = member.user_id === me.user_id ? " <small>(you)</small>" : "";

    html += `
          <tr>
            <td>${escapeHtml(member.display_name)}${you}</td>
            <td>${action}</td>
          </tr>`;
  }
  document.getElementById("membersList").innerHTML = html;
}

// Removes a member from the group and refreshes the list. This can only
// be done by group leaders.
function removeMember(userId) {
  if (!confirm("Remove this member from the group?")) return;

  api("removemember.php", { group_id: groupId, user_id: userId })
    .then(output => {
      if (output === 0) {
        alert("Couldn't remove that member");
        return;
      }
      loadGroup();  
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Promotes a member to become a leader.
function promoteMember(userId) {
  if (!confirm("Make this member a leader?")) return;

  api("promote.php", { group_id: groupId, user_id: userId })
    .then(output => {
      if (output === 0) {
        alert("Couldn't promote that member");
        return;
      }
      loadGroup();  
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Leaves the group. In the case that the last/only leader wants to leave the group,
// they must promote someone first. If the last remaining member leaves the group,
// the group is deleted.
function leaveGroup() {
  if (!confirm("Are you sure you want to leave this group?")) return;

  api("leavegroup.php", { group_id: groupId })
    .then(output => {
      if (output === 0) { alert("Couldn't leave this group"); return; }

      // Only leader with other members: leader must promote someone before leaving.
      if (output.error === "promote") {
        alert("You're the only leader. Promote another member before leaving.");
        return;
      }

      // Last member left in the group, so leaving deletes the group entirely.
      if (output.deleted_group) {
        alert("You were the last member, so the group was deleted.");
      }

      location.href = "home.html";  // Go back to the groups list.
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Clears the file picker so a rejected/invalid .txt file doesn't stay selected. 
function clearFile() {
  document.getElementById("noteFile").value = "";
}

// Reads and uploads the selected .txt file as a new note.
async function uploadNote(event) {
  event.preventDefault();

  const file = document.getElementById("noteFile").files[0];
  if (!file) { alert("Please choose a file."); return; }

  if (!file.name.toLowerCase().endsWith(".txt")) {
    alert("Only .txt files are allowed.");
    clearFile();
    return;
  }
  if (file.size === 0) {
    alert("That file is empty.");
    clearFile();
    return;
  }
  if (file.size > 200000) {
    alert("File is too big (max 200 KB).");
    clearFile();
    return;
  }
  if (file.name.length > 200) {
    alert("File name is too long (max 200 characters).");
    clearFile();
    return;
  }

  // Disable the Upload button while uploading so a double-click can't upload twice.
  const uploadBtn = event.target.querySelector("button[type=submit]");
  uploadBtn.disabled = true;

  const content = await file.text();  // Read file's text.

  api("uploadtxt.php", { group_id: groupId, title: file.name, content: content })
    .then(output => {
      if (output === 0) { alert("Upload failed"); return; }
      document.getElementById("upload").close();
      loadGroup();   
      showNote(output.note_id);   // Open the new note in the preview.
    }).catch(err => { console.log(err); alert("Could not reach the server"); })
    .finally( () => { uploadBtn.disabled = false; });   // Re-enable whether it worked or not.
}

// Switches the preview into edit mode.
function startEdit() {
  document.getElementById("editTitle").value = currentNote.title;
  document.getElementById("editContent").value = currentNote.content;
  document.getElementById("notePreview").hidden = true;
  document.getElementById("editBtn").hidden = true;
  document.getElementById("editArea").hidden = false;
}

// Leaves edit mode without saving.
function cancelEdit() {
  document.getElementById("editArea").hidden = true;
  document.getElementById("notePreview").hidden = false;
  document.getElementById("editBtn").hidden = (currentNote === null);
}

// Saves the edit, fails if someone else saved first (version check).
function saveEdit() {
  const title = document.getElementById("editTitle").value.trim();
  const content = document.getElementById("editContent").value;
  if (title === "" || content === "") { alert("Title and content can't be empty."); return; }

  // Apply same limits as edittxt.php to ensure the server only rejects real conflicts.
  if (title.length > 200) { alert("Title is too long (max 200 characters)."); return;}
  if (content.length > 200000) { alert("Note is too long (max 200 KB)."); return;}

  api("edittxt.php", { note_id: currentNote.id, title: title, content: content, version: currentNote.version })
    .then(output => {
      if (output === 0) {
        alert("Someone else edited this note (or it was deleted). Loading the latest version.");
      }
      showNote(currentNote.id);   // Show the latest note.
      loadGroup();  
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Copies the invite code to the clipboard and briefly displays "Copied!".
// Displays the code in an alert if copying isn't allowed.
function copyCode(btn) {
  const code = document.getElementById("inviteCode").textContent;
  navigator.clipboard.writeText(code)
    .then(() => {
      btn.textContent = "Copied!";
      setTimeout(() => { btn.textContent = "Copy"; }, 1500);
    })
    .catch(() => alert("Invite code: " + code));
}