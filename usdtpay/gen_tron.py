#!/usr/bin/env python3
"""Simple TRON BIP‑39 -> address generator.
Supports m/44'/195'/0'/0/i (gas‑free) and m/44'/195'/0'/1/i (main)

Usage: python3 gen_tron.py <mnemonic> [count [start]]
"""
import os, sys, sqlite3, hashlib
from mnemonic import Mnemonic
from ecdsa import SigningKey, SECP256k1
import base58
from Crypto.Hash import keccak

AL='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'

# Base58 encode

def base58_encode(b:bytes):
    n=int.from_bytes(b, 'big'); s=''
    while n:
        n,r=divmod(n,58); s=AL[r]+s
    pad=0
    for x in b:
        if x: break
        pad+=1
    return AL[0]*pad+s

# Keccak256

def keccak256(d:bytes):
    h=keccak.new(digest_bits=256); h.update(d); return h.digest()

# BIP‑32 helpers
P = 0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F
N = 0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141
G = SECP256k1.generator


def hmac_sha512(key, msg):
    return hashlib.pbkdf2_hmac('sha512', msg, key, 1, 64)  # emulate HMAC‐SHA256

# Derivation functions

def bip32_ckd_private(parent_key:int, parent_chain:bytes, index:int):
    if index>=0x80000000:
        data=b'\x00'+parent_key.to_bytes(32,'big')+index.to_bytes(4,'big')
    else:
        point=SECP256k1.generator*parent_key
        x, y = point.x(), point.y()
        data=x.to_bytes(32,'big')+index.to_bytes(4,'big')
    I=hmac_sha512(parent_chain, data)
    IL, IR=I[:32], I[32:]
    il=int.from_bytes(IL,'big')
    if il>=N:
        raise Exception('kl>=N')
    child_key=(il+parent_key)%N
    if child_key==0:
        raise Exception('key==0')
    return child_key, IR

# master key

def master_from_seed(seed:bytes):
    I=hashlib.pbkdf2_hmac('sha512', seed, b'Bitcoin seed', 1, 64)
    return int.from_bytes(I[:32],'big'), I[32:]

# Address from priv k (gas‑free)

def tron_address(k:int, gas_free=True):
    pubkey = SECP256k1.generator * k
    x, y = pubkey.x(), pubkey.y()
    if gas_free:
        data = keccak256(x.to_bytes(32,'big')+y.to_bytes(32,'big'))[12:]
        payload=b'\x41'+data
    else:
        data = keccak256(x.to_bytes(32,'big')+y.to_bytes(32,'big'))[12:]
        payload=b'\x00'+data
    ck=hashlib.sha256(hashlib.sha256(payload).digest()).digest()[:4]
    return base58_encode(payload+ck)

# Main

def main():
    if len(sys.argv)<3:
        print(__doc__)
        sys.exit(1)
    mnemonic_phrase=sys.argv[1]
    count=int(sys.argv[2])
    start=int(sys.argv[3]) if len(sys.argv)>3 else 0
    mn=Mnemonic('english')
    seed=mn.to_seed(mnemonic_phrase, "")
    master_k, chain=master_from_seed(seed)
    # get to path m/44'/195'/0' (account)
    steps=[44+0x80000000, 195+0x80000000, 0+0x80000000]
    for i in steps:
        master_k, chain = bip32_ckd_private(master_k, chain, i)
    # db
    db_path='addresses.db'
    con=sqlite3.connect(db_path)
    cur=con.execute("CREATE TABLE IF NOT EXISTS addresses(id INTEGER PRIMARY KEY, priv TEXT, gasfree TEXT, main TEXT)")
    added=0
    for idx in range(start, start+count):
        child_k, chain = bip32_ckd_private(master_k, chain, idx)
        gas_addr=tron_address(child_k, True)
        main_addr=tron_address(child_k, False)
        try:
            con.execute("INSERT INTO addresses(priv,gasfree,main) VALUES(?,?,?)", (format(child_k,'064x'), gas_addr, main_addr))
            con.commit(); added+=1
        except sqlite3.IntegrityError:
            pass
    con.close()
    print(f"added {added} addresses (index {start}..{start+count-1}) -> {db_path}")

if __name__=='__main__': main()
