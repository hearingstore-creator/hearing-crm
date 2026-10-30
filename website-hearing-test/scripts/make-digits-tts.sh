#!/usr/bin/env bash
# Alternative to recording: generate the DIN digits with natural neural voices (Microsoft Edge TTS).
# Run on your own computer (needs internet):  pip install edge-tts  +  ffmpeg installed.
#   bash scripts/make-digits-tts.sh
# Check the voices' licence terms before using them commercially; a recorded human voice avoids that question.
set -euo pipefail
OUT="$(cd "$(dirname "$0")/.." && pwd)/public/hearing-test/digits"
BN_VOICE="${BN_VOICE:-bn-BD-NabanitaNeural}"   # or bn-BD-PradeepNeural (male)
EN_VOICE="${EN_VOICE:-en-US-JennyNeural}"
TMP="$(mktemp -d)"
declare -A BN=([1]=এক [2]=দুই [3]=তিন [4]=চার [5]=পাঁচ [6]=ছয় [7]=সাত [8]=আট [9]=নয়)
declare -A EN=([0]=zero [1]=one [2]=two [3]=three [4]=four [5]=five [6]=six [8]=eight [9]=nine)

make() { # lang voice digit word
    edge-tts --voice "$2" --rate=-5% --text "$4" --write-media "$TMP/$1$3.mp3" >/dev/null
    ffmpeg -loglevel error -y -i "$TMP/$1$3.mp3" \
        -af "silenceremove=start_periods=1:start_threshold=-45dB,areverse,silenceremove=start_periods=1:start_threshold=-45dB,areverse" \
        -ac 1 -ar 22050 -b:a 48k "$OUT/$1/$3.mp3"
    echo "  $1/$3.mp3  $4"
}
mkdir -p "$OUT/bn" "$OUT/en"
for d in "${!BN[@]}"; do make bn "$BN_VOICE" "$d" "${BN[$d]}"; done
for d in "${!EN[@]}"; do make en "$EN_VOICE" "$d" "${EN[$d]}"; done
rm -rf "$TMP"
echo "Done. Re-norm the score cut-offs (README) because the voice changed."
