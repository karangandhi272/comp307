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
      showLeaderboard(output.leaderboard, output.me);
      showQuizList(output.quizzes);
      groupNotes = output.notes;
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

    const selected = currentNote && currentNote.id == note.id ? ` class="selected"` : "";

    html += `
        <tr onclick="showNote(${note.id})" data-note-id="${note.id}"${selected} style="cursor: pointer">
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
  markSelectedNote();
  cancelEdit();
}

// Highlights the open note in the notes list.
function markSelectedNote() {
  document.querySelectorAll("#notesList tr[data-note-id]").forEach(row => {
    row.classList.toggle("selected", currentNote !== null && row.dataset.noteId == currentNote.id);
  });
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
      markSelectedNote();
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

// Constructs the leaderboard. The server sorts it by max score (best score per quiz, summed),
// and members with equal max scores share a rank.
function showLeaderboard(rows, me) {
  let html = "";
  let rank = 0;
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    if (i === 0 || row.points !== rows[i - 1].points) rank = i + 1;

    const you = row.user_id === me.user_id ? " <small>(you)</small>" : "";

    html += `
          <tr>
            <td>${rank}</td>
            <td>${escapeHtml(row.display_name)}${you}</td>
            <td>${row.notes}</td>
            <td>${row.quizzes_taken}</td>
            <td>${row.attempts}</td>
            <td>${row.first_points}/${row.possible}</td>
            <td><strong>${row.points}</strong>/${row.possible}</td>
          </tr>`;
  }
  document.getElementById("leaderboardList").innerHTML = html;
}

// Constructs the quiz list with each quiz's question count, your best score and a Take button.
function showQuizList(quizzes) {
  const table = document.getElementById("quizList");

  if (quizzes.length === 0) {
    table.innerHTML = `<tr><td><small>No quizzes yet.</small></td></tr>`;
    return;
  }

  let html = "";
  for (let i = 0; i < quizzes.length; i++) {
    const q = quizzes[i];
    const best = q.best_score === null ? "not taken" : "best " + q.best_score + "/" + q.question_count;

    html += `
          <tr>
            <td>
              ${escapeHtml(q.title)}<br>
              <small>${q.question_count} questions · ${best}</small>
            </td>
            <td><button onclick="startQuiz(${q.id}, ${q.best_score})">${q.best_score === null ? "Take" : "Retake"}</button></td>
          </tr>`;
  }
  table.innerHTML = html;
}

// The group's notes from the last loadGroup(), listed in the New quiz popup.
let groupNotes = [];

// Opens the New quiz popup with a checkbox for each note (all ticked).
function openCreateQuiz() {
  if (groupNotes.length === 0) { alert("Upload some notes first. Quizzes are made from the group's notes."); return; }

  let html = "";
  for (let i = 0; i < groupNotes.length; i++) {
    html += `
          <p><label><input type="checkbox" value="${groupNotes[i].id}" checked> ${escapeHtml(groupNotes[i].title)}</label></p>`;
  }
  document.getElementById("quizNotePicker").innerHTML = html;
  document.getElementById("createQuiz").showModal();
}

// Asks the server to generate the quiz from the ticked notes. Takes a few seconds.
function createQuiz(event) {
  event.preventDefault();

  const noteIds = [...document.querySelectorAll("#quizNotePicker input:checked")].map(box => Number(box.value));
  if (noteIds.length === 0) { alert("Pick at least one note."); return; }

  const btn = event.target.querySelector("button[type=submit]");
  btn.disabled = true;   // Generating is slow and costs a request, so no double-clicks.
  btn.textContent = "Generating…";

  api("create-quiz.php", {
    group_id: groupId,
    title: document.getElementById("quizTitleInput").value.trim(),
    question_count: document.getElementById("quizCountInput").value,
    note_ids: JSON.stringify(noteIds)
  })
    .then(output => {
      if (output === 0) { alert("Couldn't create the quiz. Check the title and notes."); return; }
      if (output.error === "cooldown") {
        alert("A quiz is being generated or was just generated for this group. Try again in " + output.wait + " seconds.");
        return;
      }
      if (output.error) { alert("The quiz generator failed. Try again in a moment."); return; }

      document.getElementById("createQuiz").close();
      event.target.reset();
      loadGroup();
    }).catch(err => { console.log(err); alert("Could not reach the server"); })
    .finally(() => { btn.disabled = false; btn.textContent = "Generate"; });
}

// The quiz being taken: { attemptId, questions, answers: {q1: "a", ...}, index, oldBest }
let quizState = null;

// Starts an attempt and opens the quiz popup on the first question.
function startQuiz(quizId, oldBest) {
  api("startquiz.php", { quiz_id: quizId })
    .then(output => {
      if (output === 0) { alert("Couldn't start that quiz"); return; }

      quizState = { attemptId: output.attempt_id, questions: output.questions, answers: {}, index: 0, oldBest: oldBest };
      document.getElementById("quizTitle").textContent = output.title;
      showQuestion();
      document.getElementById("quiz").showModal();
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Shows the current question. Options are re-checked if the user already answered it.
function showQuestion() {
  const questions = quizState.questions;
  const q = questions[quizState.index];
  const last = quizState.index === questions.length - 1;

  document.getElementById("quizProgress").textContent = "Question " + (quizState.index + 1) + " of " + questions.length;
  document.getElementById("quizPrompt").textContent = q.prompt;

  let html = "";
  for (let j = 0; j < q.options.length; j++) {
    const checked = quizState.answers[q.id] === q.options[j].id ? "checked" : "";
    html += `
          <p><label>
            <input type="radio" name="answer" onchange="pickAnswer(${j})" ${checked}>
            ${String.fromCharCode(65 + j)}. ${escapeHtml(q.options[j].text)}
          </label></p>`;
  }
  document.getElementById("quizOptions").innerHTML = html;

  document.getElementById("quizAnswered").textContent = Object.keys(quizState.answers).length + " of " + questions.length + " answered";
  document.getElementById("quizBack").disabled = quizState.index === 0;
  document.getElementById("quizNext").textContent = last ? "Submit" : "Next";
}

// Saves the picked option for the current question.
function pickAnswer(optionIndex) {
  const q = quizState.questions[quizState.index];
  quizState.answers[q.id] = q.options[optionIndex].id;
  document.getElementById("quizAnswered").textContent = Object.keys(quizState.answers).length + " of " + quizState.questions.length + " answered";
}

// Back/Next. Going past the last question submits.
function moveQuestion(step) {
  const next = quizState.index + step;
  if (next < quizState.questions.length) {
    quizState.index = next;
    showQuestion();
    return;
  }

  const unanswered = quizState.questions.length - Object.keys(quizState.answers).length;
  if (unanswered > 0 && !confirm(unanswered + " question(s) unanswered. Submit anyway?")) return;
  submitQuiz();
}

// Sends the answers, then shows the results popup and refreshes the leaderboard.
function submitQuiz() {
  const nextBtn = document.getElementById("quizNext");
  nextBtn.disabled = true;   // So a double-click can't submit twice.

  api("submitquiz.php", { attempt_id: quizState.attemptId, answers: JSON.stringify(quizState.answers) })
    .then(output => {
      if (output === 0) { alert("Couldn't submit this quiz. It may already be submitted."); return; }
      document.getElementById("quiz").close();
      showResults(output);
      loadGroup();
    }).catch(err => { console.log(err); alert("Could not reach the server"); })
    .finally(() => { nextBtn.disabled = false; });
}

// Fills the results popup: score, leaderboard points earned, and a review of every question.
function showResults(output) {
  // The leaderboard counts your best score per quiz, so you only earn points by beating it.
  const earned = Math.max(0, output.score - (quizState.oldBest ?? 0));
  document.getElementById("resultsScore").textContent = output.score + "/" + output.max_score;
  document.getElementById("resultsPoints").textContent = "+" + earned + " leaderboard points";

  // Shows an option's text from its id, e.g. "b" becomes "B. 403".
  const optionText = (q, id) => {
    const j = q.options.findIndex(o => o.id === id);
    return j === -1 ? "no answer" : String.fromCharCode(65 + j) + ". " + q.options[j].text;
  };

  let html = "";
  for (let i = 0; i < output.results.length; i++) {
    const r = output.results[i];
    const q = quizState.questions.find(q => q.id === r.id);
    const yours = r.correct ? "" : `<br><small>Your answer: ${escapeHtml(optionText(q, r.picked))}</small>`;

    html += `
          <p>
            ${r.correct ? "✓" : "✗"} <strong>${i + 1}.</strong> ${escapeHtml(r.prompt)}
            ${yours}
            <br><small>Correct: ${escapeHtml(optionText(q, r.answer))}</small>
            ${r.explanation ? `<br><small>${escapeHtml(r.explanation)}</small>` : ""}
          </p>`;
  }
  document.getElementById("resultsReview").innerHTML = html;
  document.getElementById("results").showModal();
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

// Opens an empty editor for a new note. Nothing is saved until the first Save.
function newNote() {
  resetPreview();
  document.getElementById("previewTitle").textContent = "New note";
  startEdit();
}

// Switches the preview into edit mode. With no note open, the editor starts empty for a new note.
function startEdit() {
  document.getElementById("editTitle").value = currentNote ? currentNote.title : "Untitled.txt";
  document.getElementById("editContent").value = currentNote ? currentNote.content : "";
  document.getElementById("notePreview").hidden = true;
  document.getElementById("editBtn").hidden = true;
  document.getElementById("editArea").hidden = false;
}

// Leaves edit mode without saving.
function cancelEdit() {
  document.getElementById("editArea").hidden = true;
  document.getElementById("conflictBox").hidden = true;
  document.getElementById("notePreview").hidden = false;
  document.getElementById("editBtn").hidden = (currentNote === null);
  if (currentNote === null) document.getElementById("previewTitle").textContent = "Note preview";   // Cancelled a new note.
}

// Saves the edit, fails if someone else saved first (version check).
function saveEdit() {
  const title = document.getElementById("editTitle").value.trim();
  const content = document.getElementById("editContent").value;
  if (title === "" || content === "") { alert("Title and content can't be empty."); return; }

  // Apply same limits as edittxt.php to ensure the server only rejects real conflicts.
  if (title.length > 200) { alert("Title is too long (max 200 characters)."); return;}
  if (content.length > 200000) { alert("Note is too long (max 200 KB)."); return;}

  // A new note (from "+ New note") is created on its first save.
  if (currentNote === null) {
    api("uploadtxt.php", { group_id: groupId, title: title, content: content })
      .then(output => {
        if (output === 0) { alert("Couldn't create the note"); return; }
        loadGroup();
        showNote(output.note_id);
      }).catch(err => { console.log(err); alert("Could not reach the server"); });
    return;
  }

  api("edittxt.php", { note_id: currentNote.id, title: title, content: content, version: currentNote.version })
    .then(output => {
      if (output === 0) { showConflict(); return; }
      showNote(currentNote.id);   // Show the saved note.
      loadGroup();
    }).catch(err => { console.log(err); alert("Could not reach the server"); });
}

// Someone else saved first. Keeps the user's text in the editor, shows the other version above it,
// and moves to the latest version number so the next Save goes through.
function showConflict() {
  api("getnote.php", { note_id: currentNote.id })
    .then(latest => {
      if (latest === 0) { alert("This note was deleted."); resetPreview(); loadGroup(); return; }
      currentNote = { id: currentNote.id, title: latest.title, content: latest.content, version: latest.version };
      document.getElementById("previewTitle").textContent = latest.title;
      document.getElementById("notePreview").textContent = latest.content;
      document.getElementById("theirVersion").textContent = latest.content;
      document.getElementById("conflictBox").hidden = false;
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