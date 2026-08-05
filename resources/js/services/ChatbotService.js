import axios from 'axios';

/**
 * Turns to replay to the assistant. The backend caps this too — the client
 * limit is only there to keep the request small.
 */
const HISTORY_LIMIT = 12;

let inFlight = null;

/**
 * Send one turn of the conversation.
 *
 * @param {string} message
 * @param {Array<{ role: 'user'|'assistant', content: string }>} history
 * @returns {Promise<{ message: string, actions: Array<object>, refresh: boolean, tools_used: string[] }>}
 */
export async function sendChatbotMessage(message, history = []) {
  inFlight?.abort();
  inFlight = new AbortController();

  const { data } = await axios.post(
    '/api/chatbot/message',
    {
      message,
      history: history
        .filter((entry) => entry.role === 'user' || entry.role === 'assistant')
        .filter((entry) => typeof entry.content === 'string' && entry.content.trim() !== '')
        .slice(-HISTORY_LIMIT)
        .map(({ role, content }) => ({ role, content })),
    },
    { signal: inFlight.signal },
  );

  return data?.data ?? data;
}

/** Stop waiting on the current turn (the widget is closing or resetting). */
export function abortChatbotMessage() {
  inFlight?.abort();
  inFlight = null;
}

export function isCancelled(error) {
  return axios.isCancel(error);
}

export { HISTORY_LIMIT };

export default { sendChatbotMessage, abortChatbotMessage, isCancelled, HISTORY_LIMIT };
