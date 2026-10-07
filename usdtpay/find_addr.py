#!/usr/bin/env python3
"""Find the BIP32 index (and privkey) that produced a given TRON address.
Path m/44'/195'/0'/0/i, keccak256(pubkey).
Usage: find_addr.py <target_address> [max_index]
"""
import hashlib, hmac, sys
sys.path.insert(0, "/home/gansta/joomshopping_usdt/usdtpay")
from generate_addresses import (mnemonic_to_seed, derive_master, ckd_priv,
                                parse_path, privkey_to_tron_address)

TARGET = sys.argv[1] if len(sys.argv) > 1 else "TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt"
MNEMONIC = open("/home/gansta/joomshopping_usdt/usdtpay/_mnemonic.txt").read().strip()
MAXI = int(sys.argv[2]) if len(sys.argv) > 2 else 2000

seed = mnemonic_to_seed(MNEMONIC)
k, c = derive_master(seed)
for i in parse_path("m/44'/195'/0'/0'"):
    k, c = ckd_priv(k, c, i)

for idx in range(MAXI):
    ki, ci = ckd_priv(k, c, idx)
    a = privkey_to_tron_address(ki)
    print(f"{idx}\t{a}\t{ki:064x}", flush=True)
    if a == TARGET:
        print(f"FOUND idx={idx} privkey={ki:064x}")
        sys.exit(0)
print("not found")
sys.exit(1)
