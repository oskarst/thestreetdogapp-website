/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./index.html", "./js/**/*.js"],
  theme: {
    extend: {
      colors: {
        amber: { DEFAULT: "#c8842f", light: "#e8a84c" },
        bark: "#2c2118",
        cream: "#faf7f2",
        sage: "#5a7d60",
        sand: "#f0e8da",
        stone: "#8c7b6b",
      },
      fontFamily: {
        serif: ["DM Serif Display", "Georgia", "serif"],
      },
    },
  },
  plugins: [],
};
