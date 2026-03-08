/**
 * AI Education App — AI Sidebar JavaScript
 *
 * Manages AI mode requests, chat rendering, and communication with the API.
 */

(function () {
  'use strict';

  const chatContainer = document.getElementById('ai-chat');
  const aiInput       = document.getElementById('ai-input');
  const editor        = document.getElementById('editor');

  let currentMode = 'general';

  /* -------------------------------------------------------------------- */
  /*  Chat message rendering                                              */
  /* -------------------------------------------------------------------- */

  function addChatMessage(role, content, extra) {
    const div = document.createElement('div');
    div.className = role === 'user'
      ? 'bg-indigo-50 rounded-lg p-3 text-sm text-gray-800'
      : role === 'flagged'
        ? 'bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700'
        : 'bg-gray-50 rounded-lg p-3 text-sm text-gray-700';

    if (extra) {
      const badge = document.createElement('span');
      badge.className = 'text-xs font-bold uppercase text-indigo-500 block mb-1';
      badge.textContent = extra;
      div.appendChild(badge);
    }

    const p = document.createElement('div');
    p.className = 'whitespace-pre-wrap';
    p.textContent = content;
    div.appendChild(p);

    chatContainer.appendChild(div);
    chatContainer.scrollTop = chatContainer.scrollHeight;
  }

  function addLoading() {
    const div = document.createElement('div');
    div.id = 'ai-loading';
    div.className = 'text-center text-sm text-gray-400 py-3';
    div.textContent = 'Thinking…';
    chatContainer.appendChild(div);
    chatContainer.scrollTop = chatContainer.scrollHeight;
  }

  function removeLoading() {
    const el = document.getElementById('ai-loading');
    if (el) el.remove();
  }

  /* -------------------------------------------------------------------- */
  /*  Get selected text from editor                                       */
  /* -------------------------------------------------------------------- */

  function getSelectedText() {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      const range = sel.getRangeAt(0);
      if (editor.contains(range.commonAncestorContainer)) {
        return sel.toString();
      }
    }
    return '';
  }

  /* -------------------------------------------------------------------- */
  /*  AI mode labels                                                      */
  /* -------------------------------------------------------------------- */

  const modeLabels = {
    interpreter: 'Assignment Interpreter',
    planner:     'Planner',
    brainstorm:  'Brainstorm Coach',
    outline:     'Outline Builder',
    draft_coach: 'Draft Coach',
    reasoning:   'Reasoning Checker',
    reflection:  'Reflection Mode',
    general:     'General Help',
  };

  /* -------------------------------------------------------------------- */
  /*  Send AI request                                                     */
  /* -------------------------------------------------------------------- */

  async function sendRequest(mode, message) {
    currentMode = mode || currentMode;
    const selection = getSelectedText();
    const docContent = editor.innerText;

    if (!message && !selection) {
      // If no message and no selection, provide a default message per mode
      const defaults = {
        interpreter: 'Please help me understand this assignment.',
        planner:     'Please break this assignment into steps.',
        brainstorm:  'Help me brainstorm ideas for this assignment.',
        outline:     'Help me create an outline for this assignment.',
        draft_coach: 'Please review my current writing.',
        reasoning:   'Check the reasoning in my writing.',
        reflection:  'Ask me questions about my writing choices.',
      };
      message = defaults[mode] || 'Help me with my writing.';
    }

    if (message) {
      addChatMessage('user', message, modeLabels[currentMode] || null);
    }

    addLoading();

    try {
      const res = await fetch('/api/ai.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': APP.csrfToken,
        },
        body: JSON.stringify({
          mode:             currentMode,
          message:          message || '',
          document_id:      APP.docId,
          assignment_id:    APP.assignmentId,
          selection:        selection,
          document_content: docContent,
        }),
      });

      removeLoading();

      const data = await res.json();

      if (data.flagged) {
        addChatMessage('flagged', data.response, '⚠️ Policy Notice');
      } else if (data.error) {
        addChatMessage('assistant', 'Error: ' + data.error);
      } else {
        addChatMessage('assistant', data.response);
      }
    } catch (err) {
      removeLoading();
      addChatMessage('assistant', 'Unable to reach the AI service. Please try again.');
    }
  }

  /* -------------------------------------------------------------------- */
  /*  Public API                                                          */
  /* -------------------------------------------------------------------- */

  window.aiRequest = function (mode) {
    sendRequest(mode, null);
  };

  window.sendAI = function () {
    const msg = aiInput.value.trim();
    if (!msg) return;
    aiInput.value = '';
    sendRequest(currentMode, msg);
  };

})();
