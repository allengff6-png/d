import tkinter as tk
import random
import sys
import winsound
# pyttsx3 removed due to instability
from PIL import Image, ImageTk
import urllib.request
import io
import threading
import time

# ---------------- Main Fullscreen Freeze UI ----------------
root = tk.Tk()
root.title("Windows-Defender - Security Warning")
root.attributes("-fullscreen", True)
root.overrideredirect(True)
root.configure(bg='black')  # Set a black background in case image loading fails

# ✅ Shortcut to close everything (kept!)
root.bind_all("<Control-q>", lambda e: sys.exit(0))

# ---------------- ERROR-HANDLED IMAGE LOADING ----------------
def load_image_from_url(url, target_size=None):
    """Safely loads an image from a URL with error handling."""
    try:
        with urllib.request.urlopen(url) as u:
            raw_data = u.read()
        img = Image.open(io.BytesIO(raw_data))
        if target_size:
            img = img.resize(target_size, Image.LANCZOS)
        return ImageTk.PhotoImage(img)
    except Exception as e:
        print(f"Error loading image from {url}: {e}")
        # Create a blank red error image as a fallback
        fallback_img = Image.new('RGB', (target_size or (100, 100)), color='red')
        return ImageTk.PhotoImage(fallback_img)

# Load background image safely
bg_photo = load_image_from_url(
    "https://i.ibb.co/wFhz3hv0/Screenshot-2025-08-19-010346.png",
    (root.winfo_screenwidth(), root.winfo_screenheight())
)
bg_label = tk.Label(root, image=bg_photo)
bg_label.place(x=0, y=0, relwidth=1, relheight=1)

# ✅ Cache Microsoft logo once
cached_logo = load_image_from_url(
    "https://i.ibb.co/tTRTcr3F/microsoft1.png",
    (18, 18)
)

# ---------------- POPUP FUNCTION ----------------
def create_defender_popup(x=None, y=None):
    """Creates a Defender-style popup window"""
    try:
        popup = tk.Toplevel(root)
        popup.geometry("520x360+" + str(x if x else random.randint(50, root.winfo_screenwidth() - 550)) +
                       "+" + str(y if y else random.randint(50, root.winfo_screenheight() - 400)))
        popup.configure(bg="white")
        popup.overrideredirect(True)
        popup.lift()
        popup.attributes("-topmost", True)  # More reliable than binding FocusOut
        popup.focus_force()  # Try to steal focus

        # ---------- Title Bar ----------
        title_bar = tk.Frame(popup, bg="#0078D7", height=30)
        title_bar.pack(fill="x", side="top")

        logo_label = tk.Label(title_bar, image=cached_logo, bg="#0078D7")
        logo_label.image = cached_logo  # Keep a reference!
        logo_label.pack(side="left", padx=6, pady=3)

        tk.Label(
            title_bar,
            text="Windows Defender Security Center",
            bg="#0078d7",
            fg="white",
            font=("Segoe UI", 10, "bold")
        ).pack(side="left", pady=3)

        btn_frame = tk.Frame(title_bar, bg="#0078d7")
        btn_frame.pack(side="right", padx=2)

        for txt, col, w in [("—", "#0078D7", 5), ("▢", "#0078D7", 5), ("✕", "#0078D7", 5)]:
            tk.Label(
                btn_frame,
                text=txt,
                font=("Segoe UI", 10, "bold"),
                bg=col,
                fg="white",
                width=w,
                height=1
            ).pack(side="left", padx=1, pady=1)

        # ---------- Header ----------
        header = tk.Frame(popup, bg="white")
        header.pack(fill="x", padx=20, pady=15)

        tk.Label(
            header,
            text="Threat Detected!",
            font=("Segoe UI", 13, "bold"),
            fg="red",
            bg="white"
        ).pack(anchor="w")

        tk.Label(
            header,
            text="App: Ads.financetrack(2).dll\nAlert level: Severe\nStatus: Active",
            font=("Segoe UI", 10),
            bg="white",
            justify="left"
        ).pack(anchor="w", pady=5)

        tk.Frame(popup, height=1, bg="#D0D0D0").pack(fill="x", padx=10, pady=10)

        # ---------- Message ----------
        tk.Label(
            popup,
            text="Access to this PC has been blocked for security reasons.\n"
                 "Please choose an action below to continue.",
            font=("Segoe UI", 10),
            bg="white",
            justify="center"
        ).pack(pady=5)

        # ---------- Buttons ----------
        btn_frame2 = tk.Frame(popup, bg="white")
        btn_frame2.pack(pady=15)

        # Buttons now create NEW popups to simulate the annoying behavior
        tk.Button(btn_frame2, text="Deny", width=14, bg="#F3F3F3", relief="groove",
                  command=lambda: create_defender_popup()).pack(side="left", padx=10)
        tk.Button(btn_frame2, text="Allow", width=14, bg="#0078D7", fg="white", relief="flat",
                  command=lambda: create_defender_popup()).pack(side="right", padx=10)

        # ---------- Bottom ----------
        bottom_bar = tk.Label(
            popup,
            text="Microsoft Defender Antivirus",
            font=("Segoe UI", 9),
            bg="#F3F3F3",
            fg="black",
            anchor="w",
            padx=10
        )
        bottom_bar.pack(side="bottom", fill="x")

    except Exception as e:
        print(f"Error creating popup: {e}") # Print error but don't crash the app

# ---------------- SIMPLE, RELIABLE SOUND ----------------
def play_alert_sound():
    """Plays a simple system beep instead of unstable voice synthesis."""
    try:
        winsound.Beep(1000, 500)  # Frequency (Hz), Duration (ms)
        # winsound.MessageBeep(winsound.MB_ICONHAND) # Alternative
    except:
        pass # If sound fails, just ignore it.

# ---------------- Spawn Popups in Batches ----------------
def spawn_many_popups(total=30, batch_size=3, delay=1500):
    """Spawn popups in batches (lighter: 3 every 1.5 seconds)"""
    def spawn_batch(remaining):
        for _ in range(min(batch_size, remaining)):
            create_defender_popup()
        play_alert_sound()  # Play the beep for the batch
        if remaining > batch_size:
            root.after(delay, lambda: spawn_batch(remaining - batch_size))
        else:
            # After all popups are spawned, show the final image
            root.after(2000, show_final_image)

    spawn_batch(total)

# ---------------- Final Image on Top ----------------
def show_final_image():
    try:
        top_photo = load_image_from_url(
            "https://i.ibb.co/qMxH8mG2/Your-paragraph-text.png",
            (600, 350)
        )

        final_popup = tk.Toplevel(root)
        final_popup.overrideredirect(True)
        final_popup.attributes("-topmost", True)

        screen_w = root.winfo_screenwidth()
        screen_h = root.winfo_screenheight()
        x = (screen_w // 2) - 300
        y = (screen_h // 2) - 175
        final_popup.geometry(f"600x350+{x}+{y}")

        lbl = tk.Label(final_popup, image=top_photo, bg="black")
        lbl.image = top_photo  # Keep a reference!
        lbl.pack(fill="both", expand=True)
    except Exception as e:
        print(f"Error showing final image: {e}")

# ---------------- Start Sequence ----------------
# Start spawning popups after a short delay
root.after(500, lambda: spawn_many_popups(15, batch_size=3, delay=1200)) # Reduced total to 15 for testing

# Start the Tkinter main event loop
try:
    root.mainloop()
except KeyboardInterrupt:
    sys.exit(0)