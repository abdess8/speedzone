/**
 * Bridges the `datetime-local` input and the API.
 *
 * The input speaks local wall-clock time with no zone attached, while the
 * backend stores instants in UTC. Posting the raw input value would make an
 * announcement outlive its end time by the reader's offset, and the management
 * table — which renders the stored instant in local time — would then show a
 * different moment than the one that was typed in.
 */

/** Local wall-clock string from the picker to the instant it denotes. */
export const toInstant = (localValue) =>
  localValue ? new Date(localValue).toISOString() : localValue;

/** A stored instant back to the wall-clock string the picker expects. */
export const toLocalInput = (iso) => {
  if (!iso) {
    return '';
  }

  const date = new Date(iso);
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

  return date.toISOString().slice(0, 16);
};

/** A sensible default window: long enough to be useful, short enough to lapse. */
export const defaultEnd = (days = 7) => {
  const date = new Date();
  date.setDate(date.getDate() + days);

  return toLocalInput(date.toISOString());
};
