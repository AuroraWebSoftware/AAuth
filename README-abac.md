todo
---

- config e aranacak model klasörü eklenecek ??
- abac rule'u eloquent'a - dönüştüren builder

- 

```json
{
    "&&": [
        {"==": ["$attribute", "asd"]},
        {"==": ["$attribute", "asd"]},
        {
            "||": 
            [
                {"==": ["$attribute", "asd"]},
                {"==": ["$attribute", "asd"]}
            ]
        }
    ]
}
```

```php

[
    "&&" =>  [
        ["==" => ["$attribute", "asd"]],
        ["==" => ["$attribute", "asd"]],
        [
            "||" => 
            [
                ["==" => ["$attribute", "asd"]],
                ["==" => ["$attribute", "asd"]]
            ]
        ]
    ]
]

```
