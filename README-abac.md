todo
---

- docs
- 

todo v2
---
- unit test improvements
- AbacUtil validation improvements
- AbacRules() and AbacArray Validation together
- interface tests


backlog
---
- config e aranacak model klasörü eklenecek ?? AStart a mı eklemek lazım?
- abac rule'u eloquent'a - dönüştüren builder ?? şimdilik scope içinden yapıldı

-----

```json
{
    "&&": [
        {
            "==": [
                "$attribute",
                "asd"
            ]
        },
        {
            "==": [
                "$attribute",
                "asd"
            ]
        },
        {
            "||": [
                {
                    "==": [
                        "$attribute",
                        "asd"
                    ]
                },
                {
                    "==": [
                        "$attribute",
                        "asd"
                    ]
                }
            ]
        }
    ]
}
```

```php

[
    "&&" => [
                ["==" => [ "attribute" => "$attribute", "value" => "asasd"]]
            ],
            ["||" =>
                [
                    ["==" => [ "attribute" => "$attribute", "value" => "asasd"]],
                ]
            ]
    ]
]

```
