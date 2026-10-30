"""
Generate the Digits-in-Noise voice files with natural neural voices (Microsoft Edge TTS).

Works on Windows, macOS and Linux. Needs Python 3.8+ and internet. No ffmpeg needed:
the hearing test trims silence and matches loudness itself when it loads the files.

    pip install edge-tts
    python make_digits_tts.py                 # Bangla (female) + English
    python make_digits_tts.py --bn-voice bn-BD-PradeepNeural   # Bangla male voice

Files are written to ../public/hearing-test/digits/bn and /en, replacing the placeholders.
Check the voice licence terms before commercial use.
"""
import argparse
import asyncio
import pathlib
import sys

try:
    import edge_tts
except ImportError:
    sys.exit("edge-tts is not installed. Run:  pip install edge-tts")

# Same digit sets as the test: English drops "seven", Bangla drops "shunno" (two syllables).
DIGITS = {
    "bn": {1: "এক", 2: "দুই", 3: "তিন", 4: "চার", 5: "পাঁচ", 6: "ছয়", 7: "সাত", 8: "আট", 9: "নয়"},
    "en": {0: "zero", 1: "one", 2: "two", 3: "three", 4: "four", 5: "five", 6: "six", 8: "eight", 9: "nine"},
}

OUT = pathlib.Path(__file__).resolve().parent.parent / "public" / "hearing-test" / "digits"


async def make(lang, voice, rate):
    folder = OUT / lang
    folder.mkdir(parents=True, exist_ok=True)
    for digit, word in DIGITS[lang].items():
        target = folder / f"{digit}.mp3"
        tmp = folder / f"{digit}.tmp.mp3"
        try:
            await edge_tts.Communicate(word, voice, rate=rate).save(str(tmp))
        except Exception as exc:
            tmp.unlink(missing_ok=True)
            sys.exit(f"Could not reach the voice service ({exc.__class__.__name__}). Check your internet connection; no files were changed for '{word}'.")
        if tmp.stat().st_size == 0:
            tmp.unlink()
            sys.exit(f"The voice service returned no audio for '{word}'. Check the voice name.")
        tmp.replace(target)  # only replace the placeholder once the new file is complete
        wav = folder / f"{digit}.wav"
        if wav.exists():
            wav.unlink()  # MP3 is loaded first; remove an old WAV so there is one source per digit
        print(f"  {lang}/{digit}.mp3  {word}")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--bn-voice", default="bn-BD-NabanitaNeural", help="or bn-BD-PradeepNeural (male)")
    ap.add_argument("--en-voice", default="en-US-JennyNeural", help="e.g. en-GB-SoniaNeural, en-IN-NeerjaNeural")
    ap.add_argument("--rate", default="-5%", help="speaking rate, e.g. -10%%")
    ap.add_argument("--only", choices=["bn", "en"], help="generate one language only")
    args = ap.parse_args()

    for lang, voice in (("bn", args.bn_voice), ("en", args.en_voice)):
        if args.only and args.only != lang:
            continue
        print(f"{lang}: {voice}")
        asyncio.run(make(lang, voice, args.rate))
    print("Done. Re-norm the score cut-offs (see README) because the voice changed.")


if __name__ == "__main__":
    main()
