/**
 * AI Education App — Editor JavaScript
 *
 * Handles the contenteditable editor: formatting, autosave, word count, and document CRUD.
 */

(function () {
  'use strict';

  const editor       = document.getElementById('editor');
  const titleInput   = document.getElementById('doc-title');
  const saveStatus   = document.getElementById('save-status');
  const wordCountEl  = document.getElementById('word-count');

  let autosaveTimer  = null;
  const AUTOSAVE_MS  = 5000;

  /* -------------------------------------------------------------------- */
  /*  Formatting commands                                                 */
  /* -------------------------------------------------------------------- */

  window.execCmd = function (cmd, value) {
    document.execCommand(cmd, false, value || null);
    editor.focus();
  };

  window.execBlock = function (tag) {
    if (tag) {
      document.execCommand('formatBlock', false, '<' + tag + '>');
      editor.focus();
    }
  };

  /* -------------------------------------------------------------------- */
  /*  Word count                                                          */
  /* -------------------------------------------------------------------- */

  function updateWordCount() {
    const text = editor.innerText.trim();
    const count = text === '' ? 0 : text.split(/\s+/).length;
    wordCountEl.textContent = count + ' word' + (count !== 1 ? 's' : '');
  }

  /* -------------------------------------------------------------------- */
  /*  Autosave                                                            */
  /* -------------------------------------------------------------------- */

  function scheduleAutosave() {
    clearTimeout(autosaveTimer);
    saveStatus.textContent = 'Unsaved changes';
    saveStatus.classList.remove('text-green-500');
    saveStatus.classList.add('text-yellow-500');
    autosaveTimer = setTimeout(saveDocument, AUTOSAVE_MS);
  }

  async function saveDocument() {
    const content = editor.innerHTML;
    const title   = titleInput.value || 'Untitled';

    // Create document if new
    if (!APP.docId) {
      try {
        const res = await fetch('/api/documents.php?action=create', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': APP.csrfToken,
          },
          body: JSON.stringify({
            title: title,
            content: content,
            assignment_id: APP.assignmentId,
          }),
        });
        const data = await res.json();
        if (data.id) {
          APP.docId = data.id;
          // Update URL without reload
          const url = new URL(window.location);
          url.searchParams.set('id', data.id);
          history.replaceState(null, '', url);
        }
      } catch (e) {
        saveStatus.textContent = 'Save failed';
        saveStatus.classList.add('text-red-500');
        return;
      }
    } else {
      // Update existing document
      try {
        await fetch('/api/documents.php?action=save', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': APP.csrfToken,
          },
          body: JSON.stringify({
            id: APP.docId,
            title: title,
            content: content,
          }),
        });
      } catch (e) {
        saveStatus.textContent = 'Save failed';
        saveStatus.classList.add('text-red-500');
        return;
      }
    }

    saveStatus.textContent = 'Saved';
    saveStatus.classList.remove('text-yellow-500', 'text-red-500');
    saveStatus.classList.add('text-green-500');
  }

  /* -------------------------------------------------------------------- */
  /*  PDF export via browser print                                        */
  /* -------------------------------------------------------------------- */

  window.exportPDF = function () {
    if (APP.docId) {
      window.open('/api/exports.php?action=html&id=' + APP.docId, '_blank');
    } else {
      // For unsaved docs, use print dialog
      const printWindow = window.open('', '_blank');
      printWindow.document.write(
        '<!DOCTYPE html><html><head><title>' + (titleInput.value || 'Document') + '</title>' +
        '<style>body{font-family:Georgia,serif;max-width:800px;margin:40px auto;padding:20px;line-height:1.6;}</style>' +
        '</head><body>' +
        '<h1>' + (titleInput.value || 'Untitled') + '</h1>' +
        editor.innerHTML +
        '</body></html>'
      );
      printWindow.document.close();
      printWindow.print();
    }
  };

  /* -------------------------------------------------------------------- */
  /*  Load document list in sidebar                                       */
  /* -------------------------------------------------------------------- */

  async function loadDocList() {
    const listEl = document.getElementById('doc-list');
    if (!listEl) return;
    try {
      const res = await fetch('/api/documents.php?action=list');
      const data = await res.json();
      if (data.documents && data.documents.length > 0) {
        listEl.innerHTML = data.documents.map(function (d) {
          const active = d.id == APP.docId ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'hover:bg-gray-50';
          return '<a href="/editor.php?id=' + d.id + '" class="block px-2 py-1 rounded ' + active + '">' +
            d.title + '</a>';
        }).join('');
      } else {
        listEl.innerHTML = '<p class="text-gray-400 text-xs">No documents yet.</p>';
      }
    } catch (e) {
      listEl.innerHTML = '<p class="text-red-400 text-xs">Failed to load.</p>';
    }
  }

  /* -------------------------------------------------------------------- */
  /*  Placeholder handling                                                */
  /* -------------------------------------------------------------------- */

  function updatePlaceholder() {
    if (editor.innerText.trim() === '') {
      editor.classList.add('is-empty');
    } else {
      editor.classList.remove('is-empty');
    }
  }

  /* -------------------------------------------------------------------- */
  /*  Init                                                                */
  /* -------------------------------------------------------------------- */

  editor.addEventListener('input', function () {
    updateWordCount();
    updatePlaceholder();
    scheduleAutosave();
  });

  titleInput.addEventListener('input', scheduleAutosave);

  updateWordCount();
  updatePlaceholder();
  loadDocList();
})();
