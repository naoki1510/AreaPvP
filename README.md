# AreaPvP

## このプラグインについて

このプラグインは、チームに分かれてエリアを奪い合い、得たポイント数を競うPvPゲームです。
キルすることでもポイントが入ります。

## コマンド

- **/pvp** チームから入退出できます。
- **/pvp info** チームメンバーの確認ができます。
- **/setsp \[チーム名\]** チームのスポーン地点を設定できます。

## TODO

1. コンフィグファイルの最適化
1. メッセージを設定可能に
1. コードの整理（主にconstruct時の引数、public/private設定）
1. エリアに乗ったときブロックをチームの色にする
1. イベントを追加し、TeamAPIを分離

## バグ

- ゲーム間のインターバルの間に/pvp を実行すると、その時点でチームに入れてしまう。