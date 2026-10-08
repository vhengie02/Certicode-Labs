"""Rebuild graphify-out/graph.json without an LLM.

Code is re-extracted with graphify's AST pass; docs/images come from the semantic
cache written by the last `/graphify` run (their AI-extracted concepts are kept,
unlike `graphify update`, which re-parses docs heuristically and drops them).
Known AST mis-resolutions are repaired by fixups.py, and community names come from
labels.json, keyed by each community's hub node so they survive renumbering.

Exits with status 2 if a doc changed since its last AI extraction; run
`/graphify . --update` in Claude Code to re-read it.

Usage (from the project root): python scripts/graphify/rebuild.py
"""
import json
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

from fixups import fix  # noqa: E402
from graphify.analyze import god_nodes, suggest_questions, surprising_connections  # noqa: E402
from graphify.build import build_from_json  # noqa: E402
from graphify.cache import check_semantic_cache  # noqa: E402
from graphify.cli import _stamped_manifest_files  # noqa: E402
from graphify.cluster import cluster, score_all  # noqa: E402
from graphify.detect import detect, save_manifest  # noqa: E402
from graphify.export import to_json  # noqa: E402
from graphify.extract import collect_files, extract  # noqa: E402
from graphify.report import generate  # noqa: E402

SPEC = Path.home() / ".claude/skills/graphify/references/extraction-spec.md"
OUT = Path("graphify-out")
LABELS = HERE / "labels.json"


def hub(G, members):
    return max(members, key=lambda node: (G.degree(node), node))


def fallback_label(G, members):
    files = Counter((G.nodes[n].get("source_file") or "").replace("\\", "/") for n in members)
    source = files.most_common(1)[0][0]
    parts = source.split("/")
    stem = parts[-1].replace(".blade.php", "").replace(".php", "")
    if source.endswith(".blade.php"):
        return f"View: {parts[-2] if len(parts) > 1 else ''}/{stem}"
    if source.startswith("config/"):
        return f"Config: {stem}"
    return stem or "Misc"


def main() -> int:
    detection = detect(Path("."))
    files = detection["files"]
    semantic_files = [f for kind in ("document", "paper", "image") for f in files.get(kind, [])]
    nodes, edges, hyperedges, stale = check_semantic_cache(semantic_files, root=".", prompt_file=str(SPEC))
    if stale:
        print("These docs changed since their last AI extraction:")
        print("\n".join(f"  {path}" for path in stale))
        print("Run in Claude Code: /graphify . --update --obsidian --obsidian-dir <vault dir>")
        return 2

    code = []
    for path in files.get("code", []):
        code.extend(collect_files(Path(path)) if Path(path).is_dir() else [Path(path)])
    ast = extract(code, cache_root=Path("."))

    seen = {node["id"] for node in ast["nodes"]}
    merged_nodes = list(ast["nodes"]) + [n for n in nodes if n["id"] not in seen and not seen.add(n["id"])]
    extraction = fix({"nodes": merged_nodes, "edges": ast["edges"] + edges, "hyperedges": hyperedges,
                      "input_tokens": 0, "output_tokens": 0}, Path("."))

    G = build_from_json(extraction, root=".", directed=False)
    communities = cluster(G)
    cohesion = score_all(G, communities)
    saved = json.loads(LABELS.read_text(encoding="utf-8")) if LABELS.exists() else {}
    labels = {cid: saved.get(hub(G, members)) or fallback_label(G, members) for cid, members in communities.items()}

    if not to_json(G, communities, str(OUT / "graph.json"), force=True, community_labels=labels):
        print("graph.json was not written")
        return 1
    report = generate(G, communities, cohesion, labels, god_nodes(G), surprising_connections(G, communities),
                      detection, {"input": 0, "output": 0}, ".",
                      suggested_questions=suggest_questions(G, communities, labels))
    (OUT / "GRAPH_REPORT.md").write_text(report, encoding="utf-8")
    (OUT / ".graphify_labels.json").write_text(json.dumps({str(k): v for k, v in labels.items()}, ensure_ascii=False),
                                               encoding="utf-8")
    (OUT / ".graphify_analysis.json").unlink(missing_ok=True)

    manifest = _stamped_manifest_files(files, extraction, Path("."))
    save_manifest(manifest, root=".", scan_corpus={f for group in files.values() for f in group})
    print(f"{datetime.now(timezone.utc):%Y-%m-%d %H:%M}Z rebuilt: {G.number_of_nodes()} nodes, "
          f"{G.number_of_edges()} edges, {len(communities)} communities")
    return 0


if __name__ == "__main__":
    sys.exit(main())
