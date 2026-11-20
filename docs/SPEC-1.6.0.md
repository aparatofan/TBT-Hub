# TBT Hub 1.6.0 — Tokens 2.2: soft and faint ink

TBT Swipe, Translation Drill and TBT Fill already define two text colours that Hub's tokens lack, as `--tbt-ink-soft` (#4E6689: phonetics, secondary lines) and `--tbt-ink-faint` (#8299B8: hints, card tags) in their private token files. TBT Flashcards uses the same two colours, as `--text-secondary: #4e6689` and a hard-coded `#8299b8`. TBT Dictionary 0.1.0 needs both and may not define a colour of its own, so Hub adopts them, the way tokens 2.1 adopted Notes' decorative palette.

Additive only. No existing value changes, so every consumer's vendored 2.1 copy stays a valid subset. Same names as Swipe, Drill and Fill, so a later migration needs no rename. Swipe's private vocabulary is not touched.

## Tasks

1. `assets/css/tbt-tokens.css`: version line → 2.2; add a note under the 2.1 note ("2.2 is additive only: soft and faint ink, already used by Swipe, Drill and Flashcards, needed by Dictionary."); in the Typography Ink group, after `--tbt-muted`:

   ```css
   --tbt-ink-soft:     #4E6689;  /* phonetics, secondary lines */
   --tbt-ink-faint:    #8299B8;  /* hints, tags, placeholders  */
   ```

2. `TBT_HUB_VERSION` and header → 1.6.0 (cache buster).
3. Report the blast radius: which repos vendor `tbt-tokens.css`. They keep working on 2.1; list them as "sync to 2.2 when next released". Do NOT edit them in this release.

## Acceptance (live)

- [ ] `tbt-tokens.css?ver=1.6.0` contains the two new tokens; every existing tool looks unchanged.
