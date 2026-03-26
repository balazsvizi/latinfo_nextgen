import re
import urllib.request


def extract(url: str) -> dict[str, str]:
    html = urllib.request.urlopen(url, timeout=7).read().decode("utf-8", "ignore")
    keys = [
        "og:type",
        "og:site_name",
        "og:title",
        "og:description",
        "og:url",
        "og:image",
        "og:image:secure_url",
        "og:image:width",
        "og:image:height",
        "og:locale",
        "twitter:card",
        "twitter:title",
        "twitter:description",
        "twitter:image",
    ]
    out: dict[str, str] = {}
    for k in keys:
        m = re.search(
            rf'(<meta\s+(?:property|name)="{re.escape(k)}"[^>]*>)',
            html,
            flags=re.IGNORECASE,
        )
        out[k] = m.group(1) if m else "(missing)"
    return out


def show(url: str) -> None:
    print("\nURL:", url)
    data = extract(url)
    for k, v in data.items():
        print(k, "=>", v)


if __name__ == "__main__":
    show("http://Alatinfo.test/lanueva/")
    show("http://localhost/Alatinfo/lanueva/")

