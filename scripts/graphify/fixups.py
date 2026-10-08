"""Repair known graphify AST mis-resolutions in an extraction or graph.json.

graphify's PHP/TS extractor resolves `$obj->method()` by name, so a controller
method that calls a same-named method on a model (e.g. LaboratoryController::openLive
calling `$laboratory->openLive()`) gets wired back to itself as a self-loop.

This script:
  - retargets those self-loops to the model method when exactly one matches,
  - drops them when the callee is inherited (e.g. Eloquent `->update()`),
  - keeps genuine recursion (`$this->method()`, `this.method()`, `$child->method()`
    inside the same class),
  - resolves doc-side guesses at code nodes (e.g. `todo_labsessioncontroller`) to the
    matching AST class node,
  - drops edges whose endpoints still are not nodes (external imports such as `vscode`).

Usage: python fixups.py <graph-or-extraction.json> [project_root]
"""
import json
import re
import sys
from pathlib import Path


def _line(root: Path, source_file: str, location: str | None) -> str:
    match = re.match(r"L(\d+)", location or "")
    path = Path(source_file) if Path(source_file).is_absolute() else root / source_file
    if not match or not path.exists():
        return ""
    lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
    index = int(match.group(1)) - 1
    return lines[index] if 0 <= index < len(lines) else ""


def _is_recursion(line: str, method: str, source_id: str) -> bool:
    """True when the call on this line really targets the enclosing method."""
    if re.search(rf"(\$this->|this\.|self::|static::){re.escape(method)}\s*\(", line, re.I):
        return True
    # Recursion through another instance of the same class (e.g. a tree walk on $child).
    owner = source_id.rsplit("_", 1)[0]
    return owner.startswith("app_models_") and re.search(rf"->{re.escape(method)}\s*\(", line, re.I) is not None


def _resolve_class(node_id: str, nodes: dict) -> str | None:
    """Map a doc-side guess like `todo_labsessioncontroller` to the AST class node
    (`app_http_controllers_api_labsessioncontroller_labsessioncontroller`)."""
    parts = node_id.split("_")
    for start in range(1, len(parts)):
        name = "_".join(parts[start:])
        matches = [n for n in nodes if n != node_id and n.endswith(f"_{name}_{name}")]
        if len(matches) == 1:
            return matches[0]
    return None


def fix(data: dict, root: Path) -> dict:
    edge_key = "links" if "links" in data else "edges"
    nodes = {n["id"]: n for n in data["nodes"]}
    kept, retargeted, resolved, dropped_loops, dropped_dangling = [], 0, 0, 0, 0

    for edge in data[edge_key]:
        source, target = edge["source"], edge["target"]
        if source not in nodes or target not in nodes:
            source = source if source in nodes else _resolve_class(source, nodes)
            target = target if target in nodes else _resolve_class(target, nodes)
            if not source or not target:
                dropped_dangling += 1
                continue
            edge = {**edge, "source": source, "target": target}
            resolved += 1
        if source == target and edge.get("relation") == "calls":
            method = (nodes[source].get("label") or "").strip(".()").lower()
            line = _line(root, edge.get("source_file", ""), edge.get("source_location"))
            if method and not _is_recursion(line, method, source):
                candidates = [
                    node_id for node_id in nodes
                    if node_id.startswith("app_models_") and node_id.endswith("_" + method)
                ]
                if len(candidates) == 1:
                    edge = {**edge, "target": candidates[0], "confidence": "INFERRED", "confidence_score": 0.85}
                    retargeted += 1
                else:
                    dropped_loops += 1
                    continue
        kept.append(edge)

    data[edge_key] = kept
    print(f"fixups: retargeted {retargeted} self-loops, dropped {dropped_loops} false self-loops, "
          f"resolved {resolved} dangling edges to class nodes, dropped {dropped_dangling} unresolvable")
    return data


if __name__ == "__main__":
    target = Path(sys.argv[1])
    project_root = Path(sys.argv[2]) if len(sys.argv) > 2 else Path.cwd()
    fixed = fix(json.loads(target.read_text(encoding="utf-8")), project_root)
    target.write_text(json.dumps(fixed, indent=2, ensure_ascii=False), encoding="utf-8")
