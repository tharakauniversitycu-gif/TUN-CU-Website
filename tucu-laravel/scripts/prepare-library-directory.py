"""Prepare PDF metadata and first-page covers for a bulk library import."""
import argparse
import hashlib
import json
import re
from pathlib import Path

import pymupdf


CATEGORY_RULES = [
    ("Magazines & Periodicals", r"\b(magazine|newsletter|newspaper|periodical|the standard)\b"),
    ("Sermons & Teaching", r"\b(sermon|sermon notes|teaching outline|university cu)\b"),
    ("Children & Youth", r"\b(child(?:ren)?|youth|teen(?:ager)?|parenting|school)\b"),
    ("Relationships & Dating", r"\b(marriage|courtship|dating|singleness|single|relationship|divorce|family|love language)\b"),
    ("Prayer & Spiritual Growth", r"\b(prayer|fasting|devotion(?:al)?|worship|gratitude|spiritual growth|discipline|meditation|intimacy with god)\b"),
    ("Holy Spirit & Spiritual Gifts", r"\b(holy spirit|spirit baptism|baptism in the spirit|spiritual gifts|prophetic|tongues|healing|deliverance|miracle)\b"),
    ("Leadership & Ministry", r"\b(leadership|leader|ministry|ministry to|servant|church discipline|pastor|preach|sermon|evangelis|missions?)\b"),
    ("Bible Study & Discipleship", r"\b(bible|scripture|gospel|discipleship|theology|christology|ecclesiology|biblical|beatitudes|salvation|ethics)\b"),
    ("Church History & Apologetics", r"\b(apologetic|church history|church fathers|reformation|knox|heresy|evidence for|defen[cs]e of)\b"),
    ("Personal Finance & Business", r"\b(business|finance|money|wealth|invest(?:ing|ment)?|trading|stewardship|workplace|entrepreneur)\b"),
    ("Policies & University Documents", r"\b(policy|policies|constitution|suspension of exams|university cu)\b"),
    ("Public Affairs & History", r"\b(manifesto|policy|constitution|politic|government|history|standard newspaper|university|memo)\b"),
    ("Education & Reference", r"\b(assignment|examinations?|tree traversal|academic|study guide|handbook)\b"),
    ("Christian Living", r"\b(christian|faith|jesus|god|grace|hope|contentment|purpose|peace|wisdom|character|life of)\b"),
]


def clean_text(value, limit=1200):
    value = re.sub(r"\s+", " ", str(value or "")).strip()
    value = re.sub(r"[\x00-\x1f\x7f]", " ", value)
    return value[:limit].strip()


def filename_title(path):
    title = path.stem
    title = re.sub(r"\s*\((?:z[- ]?library|z[- ]?lib(?:rary)?\.org)\)\s*", "", title, flags=re.I)
    title = re.sub(r"\s*\[[^\]]*(?:z[- ]?lib|z[- ]?library)[^\]]*\]\s*", "", title, flags=re.I)
    title = title.replace("_", " ")
    title = re.sub(r"\s+-\s*\d+$", "", title)
    title = re.sub(r"\s+", " ", title).strip(" .-_")
    return clean_text(title, 255) or "Untitled book"


def usable_metadata_title(value, fallback):
    title = clean_text(value, 255)
    if not title or len(title) < 3 or title.lower() in {"untitled", "unknown", "document"}:
        return fallback
    if title.lower().startswith("untitled"):
        return fallback
    return title


def extract_author(metadata_author, filename):
    author = clean_text(metadata_author, 255)
    if author and author.lower() not in {
        "unknown", "administrator", "user", "hp", "microsoft office user", "microsoft word",
    }:
        return author

    matches = re.findall(r"\(([^()]*)\)", filename.stem)
    for match in matches:
        candidate = clean_text(match, 255)
        if candidate and not re.search(r"z[- ]?lib|edition|volume|vol\.", candidate, re.I):
            parts = candidate.split()
            if 1 <= len(parts) <= 8 and any(char.isalpha() for char in candidate):
                return candidate

    stripped = re.sub(r"\([^)]*\)|\[[^\]]*\]", " ", filename.stem)
    for separator in (" - ", " — ", " – "):
        if separator in stripped:
            candidate = clean_text(stripped.rsplit(separator, 1)[-1], 255)
            if len(candidate.split()) <= 8 and len(candidate) <= 100:
                return candidate
    return ""


def category_for(title, subject, filename):
    haystack = f"{title} {subject} {filename}".lower()
    for category, expression in CATEGORY_RULES:
        if re.search(expression, haystack, re.I):
            return category
    return "Christian Living"


def description_for(title, author, subject, opening_text, category):
    subject = clean_text(subject, 300)
    if subject and subject.lower() not in {title.lower(), "untitled"}:
        description = subject
    else:
        candidates = []
        for paragraph in re.split(r"[\r\n]+", opening_text):
            paragraph = clean_text(paragraph, 600)
            lower = paragraph.lower()
            if len(paragraph) < 90 or len(paragraph) > 500:
                continue
            if any(term in lower for term in (
                "copyright", "all rights reserved", "isbn", "published by", "printed in",
                "table of contents", "contents", "scripture quotations", "no part of this book",
            )):
                continue
            if paragraph.lower() == title.lower() or (author and paragraph.lower() == author.lower()):
                continue
            candidates.append(paragraph)
            if len(candidates) == 2:
                break
        if candidates:
            description = " ".join(candidates)
        else:
            byline = f" by {author}" if author else ""
            description = f"A resource in {category.lower()}{byline}."

    return clean_text(description, 1000)


def sha256_file(path):
    digest = hashlib.sha256()
    with path.open("rb") as file:
        for chunk in iter(lambda: file.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def inspect_pdf(path, cover_dir):
    fallback_title = filename_title(path)
    record = {
        "source_path": str(path.resolve()),
        "filename": path.name,
        "byte_size": path.stat().st_size,
        "sha256": sha256_file(path),
        "title": fallback_title,
        "author": "",
        "description": "",
        "category": "Christian Living",
        "page_count": 0,
        "encrypted": False,
        "validation_status": "review",
        "cover_path": None,
    }

    try:
        with pymupdf.open(path) as document:
            metadata = document.metadata or {}
            record["title"] = usable_metadata_title(metadata.get("title"), fallback_title)
            record["author"] = extract_author(metadata.get("author"), path)
            record["encrypted"] = bool(document.needs_pass)
            if document.needs_pass:
                record["description"] = description_for(
                    record["title"], record["author"], metadata.get("subject"), "", "Christian Living"
                )
                return record
            if document.page_count < 1:
                return record

            record["page_count"] = document.page_count
            record["validation_status"] = "valid"
            opening_text = "\n".join(
                document[index].get_text() for index in range(min(8, document.page_count))
            )
            record["category"] = category_for(
                record["title"], metadata.get("subject", ""), fallback_title
            )
            record["description"] = description_for(
                record["title"], record["author"], metadata.get("subject", ""), opening_text, record["category"]
            )

            page = document.load_page(0)
            bounds = page.rect
            if bounds.width > 0 and bounds.height > 0:
                scale = min(3.0, 900 / bounds.width, 1200 / bounds.height)
                image = page.get_pixmap(
                    matrix=pymupdf.Matrix(scale, scale),
                    colorspace=pymupdf.csRGB,
                    alpha=False,
                )
                if image.width < 600 or image.height < 800:
                    canvas = pymupdf.Pixmap(
                        pymupdf.csRGB, pymupdf.IRect(0, 0, 900, 1200), False
                    )
                    canvas.clear_with(248)
                    x = (canvas.width - image.width) // 2
                    y = (canvas.height - image.height) // 2
                    canvas.copy(image, pymupdf.IRect(x, y, x + image.width, y + image.height))
                    image = canvas
                cover_dir.mkdir(parents=True, exist_ok=True)
                cover = cover_dir / f"{record['sha256']}.jpg"
                image.save(cover, jpg_quality=88)
                if image.width >= 600 and image.height >= 800 and cover.stat().st_size <= 8 * 1024 * 1024:
                    record["cover_path"] = str(cover.resolve())
                else:
                    cover.unlink(missing_ok=True)
    except Exception as error:
        record["error"] = clean_text(error, 500)

    if not record["description"]:
        record["description"] = description_for(
            record["title"], record["author"], "", "", record["category"]
        )
    return record


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", action="append", required=True, help="Directory to scan; can be repeated")
    parser.add_argument("--output", required=True, help="Manifest JSON output path")
    parser.add_argument("--covers", required=True, help="Directory for generated covers")
    args = parser.parse_args()

    source_roots = [Path(value).resolve() for value in args.source]
    output = Path(args.output).resolve()
    cover_dir = Path(args.covers).resolve()
    seen = set()
    records = []
    for root in source_roots:
        if not root.is_dir():
            raise SystemExit(f"Source directory does not exist: {root}")
        for path in sorted(root.rglob("*.pdf")):
            if not path.is_file() or path.is_symlink():
                continue
            resolved = path.resolve()
            if resolved in seen:
                continue
            seen.add(resolved)
            records.append(inspect_pdf(resolved, cover_dir))

    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({
        "format": 1,
        "source_roots": [str(root) for root in source_roots],
        "cover_root": str(cover_dir),
        "books": records,
    }, ensure_ascii=False, indent=2), encoding="utf-8")

    categories = {}
    failures = 0
    for book in records:
        categories[book["category"]] = categories.get(book["category"], 0) + 1
        failures += book["validation_status"] != "valid"
    print(json.dumps({
        "books": len(records),
        "categories": categories,
        "needs_review": failures,
        "covers": sum(book["cover_path"] is not None for book in records),
        "manifest": str(output),
    }, ensure_ascii=False))


if __name__ == "__main__":
    main()
