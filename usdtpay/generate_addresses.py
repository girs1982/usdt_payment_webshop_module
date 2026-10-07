#!/usr/bin/env python3
"""Generate TRON (TRC20) addresses from BIP39 mnemonic.
Derives m/44'/195'/0'/0/i keypairs, inserts into MySQL `addresses` table.

Usage: python3 generate_addresses.py <mnemonic> <count> [start_index]
"""
import hashlib, hmac, sqlite3, sys, time

# ---------- BIP39 ----------
_WORDLIST_PATH = "/home/gansta/joomshopping_usdt/usdtpay/english.txt"

def _load_wordlist():
    with open(_WORDLIST_PATH) as f:
        return [w.strip() for w in f if w.strip()]

WORDLIST = _load_wordlist()

def mnemonic_to_seed(mnemonic: str, passphrase: str = "") -> bytes:
    words = mnemonic.split()
    if len(words) not in (12, 15, 18, 21, 24):
        raise ValueError(f"invalid word count: {len(words)}")
    for w in words:
        if w not in WORDLIST:
            raise ValueError(f"invalid word: {w}")
    # pack 11-bit indices into a bitstream
    bits = 0
    for w in words:
        bits = (bits << 11) | WORDLIST.index(w)
    total_bits = len(words) * 11
    ent_bits = total_bits - len(words) // 3   # checksum = 1 bit per 3 words
    entropy = bits >> (total_bits - ent_bits)
    entropy = entropy.to_bytes(ent_bits // 8, "big")
    salt = ("mnemonic" + passphrase).encode("utf-8")
    return hashlib.pbkdf2_hmac("sha512", entropy, salt, 2048)

# ---------- secp256k1 ----------
P  = 0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F
N  = 0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141
Gx = 0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798
Gy = 0x483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8
G  = (Gx, Gy)

def inv(a, m):
    return pow(a, -1, m)

def pt_add(P1, P2):
    if P1 is None: return P2
    if P2 is None: return P1
    x1, y1 = P1; x2, y2 = P2
    if x1 == x2 and (y1 + y2) % P == 0: return None
    if P1 == P2:
        l = (3 * x1 * x1) * inv(2 * y1, P) % P
    else:
        l = (y2 - y1) * inv(x2 - x1, P) % P
    x3 = (l * l - x1 - x2) % P
    y3 = (l * (x1 - x3) - y1) % P
    return (x3, y3)

def pt_mul(k, Pt=G):
    R = None
    while k:
        if k & 1: R = pt_add(R, Pt)
        Pt = pt_add(Pt, Pt)
        k >>= 1
    return R

# ---------- BIP32 ----------
def ser32(i): return i.to_bytes(4, "big")
def ser256(k): return k.to_bytes(32, "big")

def ckd_priv(kpar, cpar, i):
    if i >= 0x80000000:
        data = b"\x00" + ser256(kpar) + ser32(i)
    else:
        Pt = pt_mul(kpar)
        data = (Pt[0].to_bytes(32, "big") + ser32(i))
    I = hmac.new(cpar, data, hashlib.sha512).digest()
    IL, IR = I[:32], I[32:]
    il = int.from_bytes(IL, "big")
    if il >= N: raise ValueError("IL >= N, retry with next index")
    ki = (il + kpar) % N
    if ki == 0: raise ValueError("ki == 0, retry with next index")
    return ki, IR

def parse_path(p):
    parts = []
    for x in p.split("/")[1:]:
        if x.endswith("'"): parts.append(int(x[:-1]) + 0x80000000)
        else: parts.append(int(x))
    return parts

def derive_master(seed):
    I = hmac.new(b"Bitcoin seed", seed, hashlib.sha512).digest()
    return int.from_bytes(I[:32], "big"), I[32:]

# ---------- TRON ----------
def base58_encode(data: bytes) -> str:
    ALPHABET = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"
    n = int.from_bytes(data, "big")
    out = ""
    while n > 0:
        n, r = divmod(n, 58)
        out = ALPHABET[r] + out
    pad = 0
    for b in data:
        if b == 0: pad += 1
        else: break
    return ALPHABET[0] * pad + out

def privkey_to_tron_address(priv: int) -> str:
    X, Y = pt_mul(priv)
    pub = X.to_bytes(32, "big") + Y.to_bytes(32, "big")
    k = keccak256(pub)
    payload = b"\x41" + k[12:]
    checksum = hashlib.sha256(hashlib.sha256(payload).digest()).digest()[:4]
    return base58_encode(payload + checksum)

def keccak256(b: bytes) -> bytes:
    # minimal keccak-f[1600]; Keccak-256 = rate 1088 (136 bytes), no suffix
    RC = [0x0000000000000001,0x0000000000008082,0x800000000000808A,0x8000000080008000,
          0x000000000000808B,0x0000000080000001,0x8000000080008081,0x8000000000008009,
          0x000000000000008A,0x0000000000000088,0x0000000080008009,0x000000008000000A,
          0x000000008000808B,0x800000000000008B,0x8000000000008089,0x8000000000008003,
          0x8000000000008002,0x8000000000000080,0x000000000000800A,0x800000008000000A,
          0x8000000080008081,0x8000000000008080,0x0000000080000001,0x8000000080008008]
    R = [[0]*5 for _ in range(5)]
    C = [[0]*5 for _ in range(5)]
    D = [0]*5
    B = [[0]*5 for _ in range(5)]

    def rol64(x, n):
        n %= 64
        return ((x << n) | (x >> (64 - n))) & 0xFFFFFFFFFFFFFFFF

    def keccak_f(A):
        for rnd in range(24):
            # theta
            for x in range(5):
                C[x] = A[x][0] ^ A[x][1] ^ A[x][2] ^ A[x][3] ^ A[x][4]
            for x in range(5):
                D[x] = C[(x+4) % 5] ^ rol64(C[(x+1) % 5], 1)
            for x in range(5):
                for y in range(5):
                    A[x][y] ^= D[x]
            # rho + pi
            for x in range(5):
                for y in range(5):
                    B[y][(2*x + 3*y) % 5] = rol64(A[x][y], ((x * 5 + y) * 7) % 64)
            # chi
            for x in range(5):
                for y in range(5):
                    A[x][y] = B[x][y] ^ ((~B[(x+1) % 5][y]) & B[(x+2) % 5][y])
            # iota
            A[0][0] ^= RC[rnd]
        return A

    rate = 136
    state = [[0]*5 for _ in range(5)]
    # absorb
    padded = b + b"\x01" + b"\x00" * ((rate - len(b) - 1) % rate) + b"\x80"
    for off in range(0, len(padded), rate):
        block = padded[off:off+rate]
        for i in range(rate // 8):
            lane = int.from_bytes(block[i*8:(i+1)*8], "little")
            state[i % 5][i // 5] ^= lane
        state = keccak_f(state)
    # squeeze 32 bytes
    out = b""
    while len(out) < 32:
        for i in range(rate // 8):
            out += state[i % 5][i // 5].to_bytes(8, "little")
            if len(out) >= 32: break
        if len(out) < 32: state = keccak_f(state)
    return out[:32]

# ---------- DB ----------
def init_db(path):
    con = sqlite3.connect(path)
    con.execute("""CREATE TABLE IF NOT EXISTS addresses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        address TEXT NOT NULL UNIQUE,
        privkey TEXT NOT NULL,
        assigned INTEGER NOT NULL DEFAULT 0,
        swept INTEGER,
        sweep_tx TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP)""")
    con.commit()
    return con

def main():
    if len(sys.argv) < 3:
        print(__doc__); sys.exit(1)
    mnemonic = sys.argv[1].strip()
    count = int(sys.argv[2])
    start = int(sys.argv[3]) if len(sys.argv) > 3 else 0
    dbpath = sys.argv[4] if len(sys.argv) > 4 else "addresses.db"

    seed = mnemonic_to_seed(mnemonic)
    mk, mc = derive_master(seed)

    # m/44'/195'/0'
    k, c = mk, mc
    for i in parse_path("m/44'/195'/0'"):
        k, c = ckd_priv(k, c, i)

    con = init_db(dbpath)
    cur = con.cursor()
    added = 0
    for idx in range(start, start + count):
        ki, ci = ckd_priv(k, c, idx)
        addr = privkey_to_tron_address(ki)
        try:
            cur.execute("INSERT INTO addresses (address, privkey, assigned) VALUES (?,?,0)",
                        (addr, ser256(ki).hex()))
            added += 1
        except sqlite3.IntegrityError:
            pass  # already exists
    con.commit()
    con.close()
    print(f"added {added} addresses (index {start}..{start+count-1}) -> {dbpath}")

if __name__ == "__main__":
    main()
